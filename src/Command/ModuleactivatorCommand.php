<?php declare(strict_types=1);

namespace ProudCommerce\OxidConsoleModuleactivator\Command;

use OxidEsales\EshopCommunity\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Dao\ShopConfigurationDaoInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Setup\Service\ModuleActivationServiceInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\State\ModuleStateServiceInterface;
use OxidEsales\EshopCommunity\Internal\Transition\Utility\ContextInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class ModuleactivatorCommand extends Command
{
    /**
     * Module activation priorities
     *
     * @var array
     */
    private $aPriorities = [];
    /**
     * Shop ids in YAML
     *
     * @var array
     */
    private $aYamlShopIds = [];

    /**
     * @var ShopConfigurationDaoInterface
     */
    private $shopConfigurationDao;

    /**
     * @var ContextInterface
     */
    private $context;

    /**
     * @var ModuleActivationServiceInterface
     */
    private $moduleActivationService;
    /**
     * @var ModuleStateServiceInterface
     */
    private $stateService;

    /**
     * @param ShopConfigurationDaoInterface    $shopConfigurationDao
     * @param ContextInterface                 $context
     * @param ModuleActivationServiceInterface $moduleActivationService
     * @param ModuleStateServiceInterface      $stateService
     */
    public function __construct(
        ShopConfigurationDaoInterface $shopConfigurationDao,
        ContextInterface $context,
        ModuleActivationServiceInterface $moduleActivationService,
        ModuleStateServiceInterface $stateService
    ) {
        parent::__construct(null);

        $this->shopConfigurationDao = $shopConfigurationDao;
        $this->context = $context;
        $this->moduleActivationService = $moduleActivationService;
        $this->stateService = $stateService;
    }

    protected function configure()
    {
        $this->setName('pc:module:activator')
        ->setDescription('Activates multiple modules, based on a YAML file')
        ->addOption('skipDeactivation', 's', InputOption::VALUE_NONE, "Skip deactivation of modules, only activate.")
        ->addOption('clearModuleData', 'd', InputOption::VALUE_NONE, "Clear module data in oxconfig.")
        ->addArgument('yaml', InputArgument::REQUIRED, 'YAML module list filename or YAML string. The file path is relative to the shop installation_root_path');

        $help = <<<HELP
<info>usage:</info>
<comment>oe-console pc:module:activator modules.yml</comment>
- to activate all modules defined in the YAML file based
on a white- or blacklist

Example:

```yaml
whitelist:
1:
- ocb_cleartmp
- moduleinternals
#- ddoevisualcms
#- ddoewysiwyg
2:
- ocb_cleartmp
priorities:
1:
moduleinternals:
    1200
ocb_cleartmp:
    950
```

Supports either a __"whitelist"__ or a __"blacklist"__ entry with multiple shop ids and the desired module ids to activate (whitelist) or to exclude from activation (blacklist).

With "priorities", you can define the order (per subshop) in which the modules will be activated.

If you want, you can also specify __a YAML string on the command line instead of a file__, e.g.:

```bash
oe-console pc:module:activator $'whitelist:\n  1:\n    - oepaypal\n' --shop-id=1
```
HELP;
        $this->setHelp($help);
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $activateShopId = $this->context->getCurrentShopId();
        $clearModuleData = $input->getOption('clearModuleData');
        if ($clearModuleData) {
            $output->writeLn("<comment>Clearing module data in DB!</comment>");
            $this->clearModuleData($activateShopId);
        }

        /* @var Symfony\Component\Console\Application $app */
        $app = $this->getApplication();
        $skipDeactivation = $input->getOption('skipDeactivation');

        // now try to read YAML
        $moduleYml = $this->getYaml($input->getArgument('yaml'));
        $moduleValues = Yaml::parse($moduleYml);
        if ($moduleValues && is_array($moduleValues)) {
            $this->aPriorities = $this->getPriorities($moduleValues, $input, $output);
            // use whitelist
            if (isset($moduleValues['whitelist'])) {
                $this->aYamlShopIds = array_keys($moduleValues['whitelist']);
                foreach ($moduleValues['whitelist'] as $shopId => $moduleIds) {
                    if ($activateShopId && $activateShopId != $shopId) {
                        $output->writeLn("<comment>Skipping shop '$shopId'!</comment>");
                        continue;
                    }

                    if (count($this->aPriorities)) {
                        $output->writeLn("<comment>Orig module order:</comment>" . print_r($moduleIds, true));
                        uasort($moduleIds, array($this, "sortModules"));
                        $output->writeLn("<comment>Sorted module order:</comment>" . print_r($moduleIds, true));
                    }

                    foreach ($moduleIds as $moduleId) {
                        if (!$this->isInstalled($moduleId)) {
                            $output->writeLn('<error>Module not found: ' . $moduleId . '</error>');
                            continue;
                        }
                        // activate
                        if (!$skipDeactivation) {
                            if ($this->stateService->isActive($moduleId, $shopId) === true) {
                                $this->moduleActivationService->deactivate($moduleId, $shopId);
                                $output->writeLn("<comment>Module '$moduleId' deactived</comment>");
                            } else {
                                $output->writeLn("<comment>Module '$moduleId' not active</comment>");
                            }
                        }
                        if ($this->stateService->isActive($moduleId, $shopId) === false) {
                            $this->moduleActivationService->activate($moduleId, $shopId);
                            $output->writeLn("<comment>Module '$moduleId' actived</comment>");
                        } else {
                            $output->writeLn("<comment>Module '$moduleId' already active</comment>");
                        }
                    }
                }
            } elseif (isset($moduleValues['blacklist'])) {
                // use blacklist
                $this->aYamlShopIds = array_keys($moduleValues['blacklist']);

                /* @var \OxidEsales\Eshop\Core\Module\ModuleList $oxModuleList  */
                $oxModuleList = oxNew(\OxidEsales\Eshop\Core\Module\ModuleList::class);
                $oConfig = \OxidEsales\Eshop\Core\Registry::getConfig();
                $aModules = $oxModuleList->getModulesFromDir($oConfig->getModulesDir());

                if (count($this->aPriorities)) {
                    $output->writeLn("<comment>Orig module order:</comment>" . print_r(array_keys($aModules), true));
                    uasort($aModules, array($this, "sortModules"));
                    $output->writeLn("<comment>Sorted module order:</comment>" . print_r(array_keys($aModules), true));
                }

                foreach ($aModules as $moduleId => $aModuleData) {
                    foreach ($moduleValues['blacklist'] as $shopId => $moduleIds) {
                        if ($activateShopId && $activateShopId != $shopId) {
                            $output->writeLn("<comment>Skipping shop '$shopId'!</comment>");
                            continue;
                        }
                        
                        if (in_array($moduleId, $moduleIds)) {
                            $output->writeLn("<comment>Module blacklisted: '$moduleId' - skipping!</comment>");
                            continue 2;
                        }
                        if (!$this->isInstalled($moduleId)) {
                            $output->writeLn('<error>Module not found: ' . $moduleId . '</error>');
                            continue 2;
                        }
                        // activate
                        if (!$skipDeactivation) {
                            if ($this->stateService->isActive($moduleId, $shopId) === true) {
                                $this->moduleActivationService->deactivate($moduleId, $shopId);
                                $output->writeLn("<comment>Module '$moduleId' deactived</comment>");
                            } else {
                                $output->writeLn("<comment>Module '$moduleId' not active</comment>");
                            }
                        }
                        if ($this->stateService->isActive($moduleId, $shopId) === false) {
                            $this->moduleActivationService->activate($moduleId, $shopId);
                            $output->writeLn("<comment>Module '$moduleId' actived</comment>");
                        } else {
                            $output->writeLn("<comment>Module '$moduleId' already active</comment>");
                        }
                    }
                }
            } else {
                $output->writeLn("<comment>No modules to activate for subshop '$shopId'!</comment>");
            }
        } else {
            $output->writeLn("<comment>No valid YAML data found!</comment>");
        }
    }

    /**
     * Get YAML string, either from file or from string
     *
     * @param string $ymlString The relative file path, from shop INSTALLATION_ROOT_PATH/oxrun_config/ OR a YAML string
     * @param string $basePath  Alternative root dir path, if a file is used
     *
     * @return string
     */
    public function getYaml($ymlString, $basePath = '')
    {
        // is it a file?
        if (strpos(strtolower($ymlString), '.yml') !== false
            || strpos(strtolower($ymlString), '.yaml') !== false
        ) {
            if ($basePath == '') {
                $basePath = $this->getConfigPath();
            }
            $ymlFile = $basePath . $ymlString;
            if (file_exists($ymlFile)) {
                $ymlString = file_get_contents($ymlFile);
            }
        }
        
        return $ymlString;
    }

    /**
     * Get OXID source path
     *
     * @return string
     */
    private function getConfigPath()
    {
        return Registry::getConfig()->getConfigParam('sShopDir') . DIRECTORY_SEPARATOR;
    }
    /**
     * @param string $moduleId
     * @return bool
     */
    private function isInstalled(string $moduleId): bool
    {
        $shopConfiguration = $this->shopConfigurationDao->get(
            $this->context->getCurrentShopId()
        );
        
        return $shopConfiguration->hasModuleConfiguration($moduleId);
    }

    /**
     * Sort modules by priority descending per subshop
     *
     * @param Module $a
     * @param Module $b
     * @return int
     */
    public function sortModules($a, $b)
    {
        $aP = $bP = 0;
        // we may have module ids in whitelist
        if (is_string($a) && is_string($b)) {
            $aID = $a;
            $bID = $b;
        } else {
            // or Module objects if using blacklist
            $aID = $a->getId();
            $bID = $b->getId();
        }
        foreach ($this->aYamlShopIds as $shopId) {
            // check if subshop priorities defined
            if (isset($this->aPriorities[$shopId])) {
                if (isset($this->aPriorities[$shopId][$aID])) {
                    $aP = $this->aPriorities[$shopId][$aID];
                }
                if (isset($this->aPriorities[$shopId][$bID])) {
                    $bP = $this->aPriorities[$shopId][$bID];
                }
            }
        }
        //die($aID . ' - ' . $bID . ' - ' . $aP . ' - ' . $bP);
        if ($aP == $bP) {
            return 0;
        }
        return ($aP > $bP) ? -1 : 1;
    }

    /**
     * Get module priorities, if any
     * @param array $moduleValues Yaml entries as array
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return array
     */
    protected function getPriorities($moduleValues, $input, $output)
    {
        $aPriorities = [];
        $activateShopId = $this->context->getCurrentShopId();
        if (isset($moduleValues['priorities'])) {
            foreach ($moduleValues['priorities'] as $shopId => $modulePrios) {
                if ($activateShopId && $activateShopId != $shopId) {
                    continue;
                }
                $aPriorities[$shopId] = $modulePrios;
            }
        }
        if (count($aPriorities)) {
            $output->writeLn("<comment>Module Priorities:</comment>");
            $output->writeLn(print_r($aPriorities, true));
        }
        return $aPriorities;
    }

    /**
     * Delete module entries from oxconfig table
     *
     * @param int $shopId
     * @return void
     */
    private function clearModuleData($shopId = false)
    {
        $sSql = "delete from oxconfig where oxvarname in (
            'aDisabledModules',
            'aLegacyModules',
            'aModuleFiles',
            'aModulePaths',
            'aModules',
            'aModuleTemplates'
        )";
        if ($shopId) {
            $sSql .= " and oxshopid = '{$shopId}'";
        }
        $database = \OxidEsales\Eshop\Core\DatabaseProvider::getDb();
        $database->execute($sSql);
    }
}