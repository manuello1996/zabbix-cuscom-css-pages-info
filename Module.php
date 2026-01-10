<?php declare(strict_types = 0);

namespace Modules\CSSandInfos;

use Zabbix\Core\CModule;
use APP;
use CMenuItem;
use CUrl;
use CController as Action;
use Modules\CSSandInfos\Actions\BaseAction;


class Module extends CModule
{
    public function getAssets(): array
    {
        $assets = parent::getAssets();

        $action = $_GET['action'] ?? '';
        if (strpos($action, 'module.pageinfo.') !== 0 && strpos($action, 'module.pagecss.') !== 0) {
            // When not module routes are requested unregister non global assets.
            $assets = [
                'js' => ['custom.js'],
                'css' => ['custom.css']
            ];
        }

        return $assets;
    }

    public function init(): void
    {
        $this->registerMenuEntry();
    }

    public function onBeforeAction(Action $action): void
    {
        if (is_a($action, BaseAction::class)) {
            $action->module = $this;
        }
    }

    protected function registerMenuEntry(): void
    {
        $administration = APP::Component()->get('menu.main')->find(_('Administration'));
        if ($administration !== null) {
            $administration->getSubMenu()->add(
                (new CMenuItem(_('Page informations')))
                    ->setUrl(
                        (new CUrl('zabbix.php'))
                            ->setArgument('action', 'module.pageinfo.list')
                    )
                    ->setAliases(['module.pageinfo.list'])
            );
            $administration->getSubMenu()->add(
                (new CMenuItem(_('Custom CSS')))
                    ->setUrl(
                        (new CUrl('zabbix.php'))
                            ->setArgument('action', 'module.pagecss.list')
                    )
                    ->setAliases(['module.pagecss.list'])
            );
        }
    }

}
