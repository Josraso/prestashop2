<?php

namespace FacturaScripts\Plugins\Prestashop\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Prestashop\Model\PrestashopConfig;
use FacturaScripts\Plugins\Prestashop\Model\PrestashopImportLog;
use FacturaScripts\Plugins\Prestashop\Lib\Actions\OrdersDownload;

/**
 * Dashboard de PrestaShop - Estadísticas y control de importaciones
 */
class DashboardPrestashop extends Controller
{
    /** @var array */
    public $statsToday = [];

    /** @var array */
    public $statsWeek = [];

    /** @var array */
    public $statsMonth = [];

    /** @var array */
    public $importsSuccess = [];

    /** @var array */
    public $importsSkipped = [];

    /** @var array */
    public $importsError = [];

    /** @var int */
    public $errorPage = 0;

    /** @var int */
    public $errorTotal = 0;

    /** @var int */
    public $errorPerPage = 50;

    /** @var PrestashopConfig */
    public $config;

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'Dashboard PrestaShop';
        $data['icon'] = 'fas fa-chart-line';
        return $data;
    }

    public function privateCore(&$response, $user, $permissions): void
    {
        parent::privateCore($response, $user, $permissions);

        // Cargar configuración
        $this->config = PrestashopConfig::getActive();

        // Obtener página de errores
        $this->errorPage = (int)$this->request->query->get('error_page', 0);

        // Procesar acciones
        $action = $this->request->request->get('action', '');
        switch ($action) {
            case 'import-now':
                $this->importNowAction();
                break;

            case 'delete-error':
                $this->deleteErrorAction();
                break;

            case 'delete-all-errors':
                $this->deleteAllErrorsAction();
                break;
        }

        // Cargar estadísticas
        $this->loadStats();
        $this->loadImportsByResult();
    }

    /**
     * Carga las estadísticas por período
     */
    private function loadStats(): void
    {
        $this->statsToday = PrestashopImportLog::getStats('today');
        $this->statsWeek = PrestashopImportLog::getStats('week');
        $this->statsMonth = PrestashopImportLog::getStats('month');
    }

    /**
     * Carga importaciones separadas por resultado
     */
    private function loadImportsByResult(): void
    {
        $logModel = new PrestashopImportLog();
        $order = ['fecha' => 'DESC', 'hora' => 'DESC'];

        // Importados correctamente
        $whereSuccess = [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('resultado', 'success')];
        $this->importsSuccess = $logModel->all($whereSuccess, $order, 0, 50);

        // Omitidos (ya importados o por fecha)
        $whereSkipped = [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('resultado', 'skipped')];
        $this->importsSkipped = $logModel->all($whereSkipped, $order, 0, 50);

        // Errores CON PAGINACIÓN
        $whereError = [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('resultado', 'error')];

        // Contar total de errores
        $this->errorTotal = $logModel->count($whereError);

        // Cargar errores de la página actual
        $offset = $this->errorPage * $this->errorPerPage;
        $this->importsError = $logModel->all($whereError, $order, $offset, $this->errorPerPage);
    }

    /**
     * Ejecuta importación manual inmediata
     */
    private function importNowAction(): void
    {
        if (!$this->permissions->allowUpdate) {
            Tools::log()->warning('No tienes permisos para ejecutar la importación');
            return;
        }

        if (!$this->config) {
            Tools::log()->error('PrestaShop no está configurado');
            return;
        }

        try {
            Tools::log()->info('========================================');
            Tools::log()->info('IMPORTACIÓN MANUAL DESDE DASHBOARD');
            Tools::log()->info('========================================');

            $importer = new OrdersDownload();
            $importer->batch('manual'); // Origen: manual

            Tools::log()->info('Importación manual completada. Revisa las estadísticas actualizadas.');

            // Recargar estadísticas después de la importación
            $this->loadStats();
            $this->loadImportsByResult();

        } catch (\Exception $e) {
            Tools::log()->error('Error en importación manual: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene el color del badge según el resultado
     */
    public function getBadgeClass(string $resultado): string
    {
        switch ($resultado) {
            case 'success':
                return 'badge-success';
            case 'error':
                return 'badge-danger';
            case 'skipped':
                return 'badge-warning';
            default:
                return 'badge-secondary';
        }
    }

    /**
     * Obtiene el texto del badge según el resultado
     */
    public function getBadgeText(string $resultado): string
    {
        switch ($resultado) {
            case 'success':
                return 'Importado';
            case 'error':
                return 'Error';
            case 'skipped':
                return 'Omitido';
            default:
                return $resultado;
        }
    }

    /**
     * Borra un error específico
     */
    private function deleteErrorAction(): void
    {
        if (!$this->permissions->allowDelete) {
            Tools::log()->warning('No tienes permisos para borrar errores');
            return;
        }

        $id = (int)$this->request->request->get('id', 0);
        if ($id <= 0) {
            Tools::log()->error('ID de error inválido');
            return;
        }

        $logModel = new PrestashopImportLog();
        if ($logModel->loadFromCode($id)) {
            if ($logModel->delete()) {
                Tools::log()->info('Error borrado correctamente');
            } else {
                Tools::log()->error('No se pudo borrar el error');
            }
        } else {
            Tools::log()->error('Error no encontrado');
        }

        // Recargar estadísticas
        $this->loadStats();
        $this->loadImportsByResult();
    }

    /**
     * Borra todos los errores
     */
    private function deleteAllErrorsAction(): void
    {
        if (!$this->permissions->allowDelete) {
            Tools::log()->warning('No tienes permisos para borrar errores');
            return;
        }

        try {
            // Usar el método correcto para obtener la base de datos
            $dataBase = new \FacturaScripts\Core\Base\DataBase();
            $sql = "DELETE FROM prestashop_import_log WHERE resultado = 'error'";

            if ($dataBase->exec($sql)) {
                Tools::log()->info('Todos los errores han sido borrados');
            } else {
                Tools::log()->error('No se pudieron borrar los errores');
            }
        } catch (\Exception $e) {
            Tools::log()->error('Error al borrar todos los errores: ' . $e->getMessage());
        }

        // Recargar estadísticas
        $this->loadStats();
        $this->loadImportsByResult();
    }

    /**
     * Obtiene el número de páginas de errores
     */
    public function getErrorPages(): int
    {
        if ($this->errorTotal == 0) {
            return 0;
        }
        return (int)ceil($this->errorTotal / $this->errorPerPage);
    }
}
