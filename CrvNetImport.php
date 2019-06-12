<?php
/**
 * Created by PhpStorm.
 * User: Jorge
 * Date: 02/11/2018
 * Time: 12:21
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

include_once dirname(__FILE__) . '/classes/CrvNetImportClass.php';

class CrvNetImport extends Module
{
    /**
     * Names of single preferences of module settings.
     */
    const PREF_FEED_ZIP = 'PREF_FEED_ZIP';
    const PREF_FEED_SUPPLIER = 'PREF_FEED_SUPPLIER';
    const PREF_FEED_STOCK = 'PREF_FEED_STOCK';
    const PREF_FEED_IMAGES = 'PREF_FEED_IMAGES';
    const PREF_FEED_DUPLICATE = 'PREF_FEED_DUPLICATE';
	const PREF_FEED_IMPORT_IMAGES = 'PREF_FEED_IMPORT_IMAGES';

    const TOTAL = 'PREF_FEED_TOTAL';
    const ACTUAL = 'PREF_FEED_ACTUAL';
    const TEXT = 'PREF_FEED_TEXT';
    /**
     * Default values for module settings.
     */
    const DEFAULT_FEED_URL = '';

    private $import;

    public function ajaxPercentageImport()
    {
        if (ob_get_length() > 0) {
            ob_end_clean();
        }
        $totalItemExport = Configuration::get(self::TOTAL);
        $totalItemExported = Configuration::get(self::ACTUAL);

        $porcentaje = (float)round($totalItemExported * 100 / $totalItemExport, 2);

        die(json_encode(array(
            'percent' => (is_float($porcentaje) ? $porcentaje: 0),
            'text' => Configuration::get(self::TEXT),
            'totalItemExport' => $totalItemExport,
            'totalItemExported' => $totalItemExported,
        )));
    }

    /**
     * @since 1.0.0
     * @see ModuleCore::__construct()
     */
    public function __construct()
    {
        $this->name = 'CrvNetImport';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Jorge Márquez';
        $this->need_instance = 1;
        $this->bootstrap = true;
        $this->_directory = dirname(__FILE__);

        parent::__construct();

        $this->displayName = $this->l('CrvNet Import XML');
        $this->description = $this->l('Module for importing XML with products from CrvNET 5.10.4.2');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    /**
     * @since 1.0.0
     * @return boolean
     * @see ModuleCore::install()
     */
    public function install()
    {
        if (!parent::install()) {
            return false;
        }
        Configuration::updateValue(self::PREF_FEED_ZIP,0);
        Configuration::updateValue(self::PREF_FEED_SUPPLIER,0);
        Configuration::updateValue(self::PREF_FEED_STOCK,0);
        Configuration::updateValue(self::PREF_FEED_IMAGES,0);
        Configuration::updateValue(self::PREF_FEED_DUPLICATE,0);
		Configuration::updateValue(self::PREF_FEED_IMPORT_IMAGES,0);
        Configuration::updateValue(self::TOTAL,0);
        Configuration::updateValue(self::ACTUAL,0);
        Configuration::updateValue(self::TEXT,"");

        return true;
    }

    /**
     * @since 1.0.0
     * @return boolean
     * @see ModuleCore::uninstall()
     */
    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }
        Configuration::deleteByName(self::PREF_FEED_ZIP);
		Configuration::deleteByName(self::PREF_FEED_SUPPLIER);
		Configuration::deleteByName(self::PREF_FEED_STOCK);
		Configuration::deleteByName(self::PREF_FEED_IMAGES);
		Configuration::deleteByName(self::PREF_FEED_DUPLICATE);
		Configuration::deleteByName(self::PREF_FEED_IMPORT_IMAGES);
        Configuration::deleteByName(self::TOTAL);
        Configuration::deleteByName(self::ACTUAL);
        Configuration::deleteByName(self::TEXT);
        return true;
    }

    /**
     * @since 1.0.0
     */
    public function getContent()
    {
        $valor = Tools::getValue('submitCrvNetImport');
        if ($valor == "1"
            || $valor == "2"
            || $valor == "3"
            || $valor == "4"
            || $valor == "5")
        {
            if (file_exists(_PS_DOWNLOAD_DIR_."ExportXML.zip")) {
                $borrardirectoriozip = Tools::getValue(self::PREF_FEED_ZIP);
                $id_supplier = Tools::getValue(self::PREF_FEED_SUPPLIER);
                $deleteimages = Tools::getValue(self::PREF_FEED_IMAGES);
                $incrementstock = Tools::getValue(self::PREF_FEED_STOCK);
                $modificarcoincidentes = Tools::getValue(self::PREF_FEED_DUPLICATE);
				$importimages = Tools::getValue(self::PREF_FEED_IMPORT_IMAGES);				
                if ($id_supplier != 0){
                    try {
                        //import process
                        $this->import = new CrvNetImportClass(_PS_DOWNLOAD_DIR_."ExportXML/", $id_supplier, $deleteimages, $incrementstock, $modificarcoincidentes, $importimages, $borrardirectoriozip);
                        $this->import->importSteps($valor);
                    } catch (Exception $e) {
                        die(json_encode(array(
                            'error' => 1,
                            'text' => $this->l('Error to import XML:'.$e->getMessage().'\n'.$e->getTraceAsString()),
                        )));
                    }
                }else{
                    die(json_encode(array(
                        'error' => 1,
                        'text' => 'Proveedor no válido, seleccione uno.',
                    )));
                }
            } else {
                die(json_encode(array(
                    'error' => 1,
                    'text' => 'Fichero XML no válido.',
                )));
            }
        }

        if (Tools::getValue('submitCrvNetImport') == "6") {
            if (ob_get_length() > 0) {
                ob_end_clean();
            }
            $this->ajaxPercentageImport();
        }

        return $this->displayForm();
    }

    /**
     * @since 1.0.0
     */
    public function displayForm()
    {
        /**
         * @var integer $default_lang
         */
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        /**
         * @var HelperForm $helper
         */
        $helper = new HelperForm();

        $suppliers = Supplier::getSuppliers(false, null, true);
        $suppliersList = [];
        if ($suppliers && count($suppliers)) {
            foreach ($suppliers as $supplier) {
                $suppliersList[] = array("id_supplier" => $supplier['id_supplier'], "name" => $supplier['name']);
            }
        }

        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cog',
            ),
            'input' => array(
                array(
                    'type' => 'switch',
                    'label' => $this->l('¿Borrar directorio de importación ZIP?'),
                    'name' => self::PREF_FEED_ZIP,
                    'class' => 't',
                    'required'  => true,
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'zip',
                            'value' => 0
                        ),
                        array(
                            'id' => 'zip',
                            'value' => 1
                        )
                    )
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Proveedor'),
                    'desc' => $this->l('Seleccione proveedor de las piezas'),
                    'required' => true,
                    'name' => self::PREF_FEED_SUPPLIER,
                    'options' => array(
                        'query' => $suppliersList,
                        'id' => 'id_supplier',
                        'name' => 'name',
                        'default' => array(
                            'value' => '0',
                            'label' => $this->l('-Elige un proveedor-')
                        )
                    )
                ),
				array(
                    'type' => 'switch',
                    'label' => $this->l('¿Modificar productos que coincidan con su código de referencia de proveedor?'),
                    'name' => self::PREF_FEED_DUPLICATE,
                    'class' => 't',
                    'required'  => true,
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'image',
                            'value' => 0
                        ),
                        array(
                            'id' => 'image',
                            'value' => 1
                        )
                    )
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Cuando un producto ya exista al importarlo, ¿Establecer el stock a 1?'),
                    'name' => self::PREF_FEED_STOCK,
                    'class' => 't',
                    'required'  => true,
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'stock',
                            'value' => 0,
                            //'label' => $this->l('Select to update all stock imported to increment in 1')
                        ),
                        array(
                            'id' => 'stock',
                            'value' => 1,
                            //'label' => $this->l('Select to update all stock imported to 1')
                        )
                    )
                ),
				array(
                    'type' => 'switch',
                    'label' => $this->l('¿Desea importar las imágenes?'),
                    'name' => self::PREF_FEED_IMPORT_IMAGES,
                    'class' => 't',
                    'required'  => true,
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'image',
                            'value' => 0,
                        ),
                        array(
                            'id' => 'image',
                            'value' => 1,
                        )
                    )
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Cuando un producto ya exista al importarlo, ¿Desea mantener las imágenes?'),
                    'name' => self::PREF_FEED_IMAGES,
                    'class' => 't',
                    'required'  => true,
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'image',
                            'value' => 0,
                            //'label' => $this->l('Si las borra')
                        ),
                        array(
                            'id' => 'image',
                            'value' => 1,
                            //'label' => $this->l('No las borra')
                        )
                    )
                )
            )
        );

        // Main properties
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->title = $this->displayName;
        $helper->submit_action = 'submit'.$this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        $this->context->controller->addCSS($this->_path .
            'views/css/datamaster.admin.css', 'all');

        // Current values
		$helper->fields_value[self::PREF_FEED_IMPORT_IMAGES] = 1;
        
		return $helper->generateForm($fields_form);
    }
}