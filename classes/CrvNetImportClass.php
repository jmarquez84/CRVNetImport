<?php
/**
 * Created by PhpStorm.
 * User: Jorge
 * Date: 02/11/2018
 * Time: 13:24
 */

include_once dirname(__FILE__) . '/ProductSupplierExtend.php';

class CrvNetImportClass{
    private $activo = "si";

    ///////////////////// FUNCIONES DE LOG
    private function escribirLog($texto) {
        if($this->activo=="si"){
            // Log
            $fecha = date('MdY');
            $logfilename = $logfilename = _PS_MODULE_DIR_."CrvNetImport/logs/log".$fecha.".log";
            $fp = @fopen($logfilename, 'a+');
            if ($fp) {
                fwrite($fp, date('M d Y G:i:s') . ' -- ' . $texto . "\r\n");
                fclose($fp);
            }
        }
    }

    private $urlFeed, $id_supplier, $deleteallimages, $incrementstock, $modificarcoincidentes, $importimages, $borrardirectoriozip;

    private function persisProduct($productos){
        $pathFileProduct = _PS_MODULE_DIR_.'CrvNetImport/docs/producto.json';
        $this->escribirLog("Guardando listado productos en ".$pathFileProduct);
        $objData = serialize($productos);

        $fp = fopen($pathFileProduct, "w");
        fwrite($fp, $objData);
        fclose($fp);
    }

    private function getProductos(){
        $pathFileProduct = _PS_MODULE_DIR_.'CrvNetImport/docs/producto.json';
        $this->escribirLog("Obteniendo listado productos de ".$pathFileProduct);
        $objData = file_get_contents($pathFileProduct);
        return unserialize($objData);
    }

    private function delete_files($target) {
        if(is_dir($target)){
            $files = glob( $target . '*', GLOB_MARK ); //GLOB_MARK adds a slash to directories returned

            foreach( $files as $file ){
                $this->delete_files( $file );
            }

            rmdir( $target );
        } elseif(is_file($target)) {
            unlink( $target );
        }
    }

    /**
     * @throws Exception
     */
    private function unzipFile(){
        $this->escribirLog("Unziping fichero...");
        $this->escribirLog("--> Empezamos borrando directorio "._PS_MODULE_DIR_.'CrvNetImport/docs/*');
        $files = glob(_PS_MODULE_DIR_.'CrvNetImport/docs/*'); // get all file names
        foreach($files as $file){ // iterate files
            if(is_file($file))
                unlink($file); // delete file
        }

        $this->escribirLog("--> Posteriormente borrando directorio ".$this->urlFeed);
        $this->delete_files($this->urlFeed);

        $file = substr ($this->urlFeed,0,strlen($this->urlFeed)-1).'.zip';
        $path = pathinfo(realpath($file), PATHINFO_DIRNAME);

        $this->escribirLog("--> Unzipping: ".$file);
        $zip = new ZipArchive;
        $res = $zip->open($file);
        if ($res === TRUE) {
            // extract it to the path we determined above
            if ($zip->extractTo($path)){
                $this->escribirLog("--> Unzipping: OK.");
            }else{
                $this->escribirLog("--> Unzipping: FAIL.: ".$zip->getStatusString());
            }
            $zip->close();
        } else {
            $this->escribirLog("--> Unzipping: KAO");
            throw new Exception("Error al descomprimir el fichero.");
        }
    }

    public function __construct($urlFeed, $id_supplier, $deleteallimages, $incrementstock, $modificarcoincidentes, $importimages, $borrardirectoriozip)
    {
        $this->escribirLog("Llamada con parámetros: [".$urlFeed."][".$id_supplier."][".$deleteallimages."][".$incrementstock."][".$modificarcoincidentes."][".$importimages."][".$borrardirectoriozip."]");
        $this->urlFeed = $urlFeed;
        $this->id_supplier = $id_supplier;
        $this->deleteallimages = $deleteallimages;
        $this->incrementstock = $incrementstock;
        $this->modificarcoincidentes = $modificarcoincidentes;
        $this->importimages = $importimages;
        $this->borrardirectoriozip = $borrardirectoriozip;
    }

    /**
     * @param $step
     * @throws Exception
     */
    public function importSteps($step){
        try {
            switch ($step) {
                case "1":
                    $this->escribirLog("Llamada a paso 1.");
                    Configuration::updateValue(CrvNetImport::TOTAL, 0);
                    Configuration::updateValue(CrvNetImport::ACTUAL, 0);
                    Configuration::updateValue(CrvNetImport::TEXT, "");

                    if ($this->borrardirectoriozip == 1) {
                        $this->unzipFile();
                    }

                    Configuration::updateValue(CrvNetImport::TEXT,"Parseando XML...");
                    die(json_encode(array(
                        'error' => 0,
                        'text' => '',
                    )));
                    break;
                case "2":
                    //parseamos el XML
                    $this->escribirLog("Llamada a paso 2.");
                    $productos = $this->parseProducts($this->urlFeed);
                    $this->persisProduct($productos);

                    $this->escribirLog("Actualizamos valores: ".count($productos));
                    Configuration::updateValue(CrvNetImport::TOTAL, count($productos));
                    Configuration::updateValue(CrvNetImport::ACTUAL,0);
                    Configuration::updateValue(CrvNetImport::TEXT,"Importando marcas...");

                    die(json_encode(array(
                        'error' => 0,
                        'text' => '',
                    )));
                    break;
                case "3":
                    //import manufactures
                    $this->escribirLog("Llamada a paso 3.");
                    $productos = $this->getProductos();

                    $this->generateManufacturers($productos);
                    Configuration::updateValue(CrvNetImport::TEXT,"Importando categorias...");
                    die(json_encode(array(
                        'error' => 0,
                        'text' => '',
                    )));
                    break;
                case "4":
                    $this->escribirLog("Llamada a paso 4.");
                    //import categories
                    $productos = $this->getProductos();

                    $this->generateCategories($productos);
                    Configuration::updateValue(CrvNetImport::TEXT,"Importando productos...(".Configuration::get(CrvNetImport::ACTUAL)." de ".Configuration::get(CrvNetImport::TOTAL).")");
                    die(json_encode(array(
                        'error' => 0,
                        'text' => '',
                    )));
                    break;
                case "5":
                    $this->escribirLog("Llamada a paso 5.");
                    $productos = $this->getProductos();

                    //import products
                    $this->importProducts($productos, $this->id_supplier, $this->deleteallimages, $this->incrementstock, $this->modificarcoincidentes, $this->importimages);
                    if (Configuration::get(CrvNetImport::TOTAL) == Configuration::get(CrvNetImport::ACTUAL)){
                        die(json_encode(array(
                            'error' => 1,
                            'text' => 'Importación con éxito!',
                        )));
                    }else{
                        die(json_encode(array(
                            'error' => 0,
                            'text' => '',
                        )));
                    }
                    break;
            }
        } catch (Exception $e) {
            $this->escribirLog("Error: ".$e->getMessage()." traza ".$e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * @param $productos
     * @param $id_supplier
     * @param $deleteallimages
     * @param $incrementstock
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function importProducts($productos, $id_supplier, $deleteallimages, $incrementstock, $modificarcoincidentes, $importimages){
        $s = 1;
        $this->escribirLog("--> Comenzamos importación desde ".Configuration::get(CrvNetImport::ACTUAL));
        for ($i = Configuration::get(CrvNetImport::ACTUAL); $i <= Configuration::get(CrvNetImport::TOTAL); $i++) {
            $producto = $productos[$i];

            $productModel = new Product();
            //check if exist
            $id_producto_existente = ProductSupplierExtend::getIdProductFromReference($producto["RefId"], $id_supplier);
            if ($id_producto_existente != null && $id_producto_existente != false){
                $productModel->id = $id_producto_existente;
                if ($modificarcoincidentes == 0){
                    continue;
                }
            }

			$textNombre = preg_replace("/[\/\&%#\<>$]/", "-", $producto["Articulo"])." ".$producto["Version"]." ".$producto["RefPieza"];
            $productModel->name = $this->createMultilanguageField($textNombre);
			if (trim($textNombre) == ""){
				$this->escribirLog("--> Salimos, ya que el producto name está vacío:".serialize($productModel));
				Configuration::updateValue(CrvNetImport::ACTUAL,$i);
				Configuration::updateValue(CrvNetImport::TEXT,"Importando productos...(".Configuration::get(CrvNetImport::ACTUAL)." de ".Configuration::get(CrvNetImport::TOTAL).")");
				continue;
			}
			
            $productModel->price = floatval($producto["Precio"]);
            if ($productModel->price > 0){
                $productModel->description_short = $this->createMultilanguageField($producto["Version"]." [".$producto["Modelo"]["Descripcion"]."(".$producto["Modelo"]["Inicio"]."-".$producto["Modelo"]["Fin"].")] ");
            }else{
                $productModel->description_short = $this->createMultilanguageField("CONSULTE POR TELÉFONO O MAIL EL PRECIO DEL PRODUCTO PARA HABILITARLO EN LA VENTA.");
            }
            $productModel->description = $this->createMultilanguageField($producto["Version"]." [".$producto["Modelo"]["Descripcion"]."(".$producto["Modelo"]["Inicio"]."-".$producto["Modelo"]["Fin"].")] ".(isset($producto["Observaciones"])? "\n\n".$producto["Observaciones"] : ""));
            $productModel->id_manufacturer = Manufacturer::getIdByName($producto["Marca"]);
            $productModel->reference = $producto["RefPieza"];
            $productModel->supplier_reference = $producto["RefId"];
            $productModel->id_supplier = $id_supplier;
            $productModel->id_tax_rules_group = 1;
            $productModel->quantity = 1;
            $productModel->quantity_discount = 1;
            $productModel->minimal_quantity = 1;
            $productModel->additional_shipping_cost = 0;
            $productModel->wholesale_price = 0;
            $productModel->ecotax = 0;
            $productModel->new = false;
            $productModel->width = 0;
            $productModel->height = 0;
            $productModel->depth = 0;
            $productModel->weight = floatval($producto["Peso"]);
            $productModel->out_of_stock = false;
            $productModel->active = true;
            $productModel->meta_description = $productModel->description;

            $product_category = $this->searchCategory(preg_replace("/[\/\&%#\<>$]/", "-", $producto["Familia"]), preg_replace("/[\/\&%#\<>$]/", "-", $producto["Articulo"]));
            $product_category_default = $this->searchCategory(preg_replace("/[\/\&%#\<>$]/", "-", $producto["Articulo"]), "");
            $productModel->id_category_default = $product_category_default[0];
            $productModel->category = $product_category;

            if ($productModel->price > 0){
                $productModel->available_for_order = true;
                $productModel->show_price = true;
            }else{
                $productModel->available_for_order = false;
                $productModel->show_price = false;
            }
            $productModel->on_sale = false;
            $productModel->online_only = false;
            $productModel->meta_keywords = $this->createMultilanguageField(preg_replace("/[\/\&%#\<>$]/", "-", $producto["Articulo"])." - ".$producto["Version"]."_".$producto["RefPieza"]);
            $productModel->tags = array($producto["Marca"], preg_replace("/[\/\&%#\<>$]/", "-", $producto["Familia"]), preg_replace("/[\/\&%#\<>$]/", "-", $producto["Articulo"]), $producto["Version"]);
            $productModel->link_rewrite = $this->createMultilanguageField(Tools::link_rewrite(preg_replace("/[\/\&%#\<>$]/", "-", $producto["Articulo"])." - ".$producto["Version"]."_".$producto["RefPieza"]));

            $this->escribirLog("--> Importamos producto ".serialize($productModel));

            $productModel->save();

            if (!isset($productModel->id) || (int)$productModel->id <= 0) {
                $this->escribirLog("--> Importación fallida");
                continue;
            }
            $this->escribirLog("--> Importación exitosa: ".$productModel->id);

            //product supplier
            $modifyProductSupplier = new ProductSupplierExtend();
            $idProductSupplier = ProductSupplierExtend::getIdByProductAndSupplier($productModel->id, 0 , $productModel->id_supplier);
            if ($idProductSupplier != null){
                $modifyProductSupplier->id = $idProductSupplier;
            }
            $modifyProductSupplier->id_product = $productModel->id;
            $modifyProductSupplier->id_product_attribute = 0;
            $modifyProductSupplier->id_supplier = $productModel->id_supplier;
            $modifyProductSupplier->product_supplier_reference = $productModel->supplier_reference;
            $modifyProductSupplier->product_supplier_price_te = $productModel->price;
            $this->escribirLog("--> Importamos supplier: ".serialize($modifyProductSupplier));
            $modifyProductSupplier->save();

            //add stock
            if ($incrementstock == 0){
                $this->escribirLog("--> Aumentamos el stock en 1");
                StockAvailable::updateQuantity($productModel->id, 0, 1);
            }else{
                $this->escribirLog("--> Establecemos el stock en 1");
                StockAvailable::setQuantity($productModel->id, 0, 1);
            }

            if ($importimages == 1) {
                $this->escribirLog("--> Importamos imágenes");
                //import images
                $this->importImages($productModel->id, $producto["Imagenes"], $deleteallimages);
            }else{
                $this->escribirLog("--> No importamos imágenes");
            }

            $this->escribirLog("--> Actualizamos categorias");
            $productModel->updateCategories($productModel->category, true);

            $this->escribirLog("--> Indexamos el producto");
            //indexar producto para las búsquedas
            Search::indexation(false, $productModel->id);

            $this->escribirLog("--> Actualizamos valores");
            Configuration::updateValue(CrvNetImport::ACTUAL,$i);
            Configuration::updateValue(CrvNetImport::TEXT,"Importando productos...(".Configuration::get(CrvNetImport::ACTUAL)." de ".Configuration::get(CrvNetImport::TOTAL).")");
            if ($s > 5){
                $this->escribirLog("--> Salimos de la tanda");
                break;
            }
            $s++;
        }
    }

    /**
     * @param $productos
     * @throws PrestaShopException
     */
    private function generateCategories($productos) {
        $categorias = array();
        $subcategorias = array();
        foreach ($productos as $producto){
			$textoFamilia = preg_replace("/[\/\&%#\<>$]/", "-", $producto["Familia"]);
			$textoSubfamilia = preg_replace("/[\/\&%#\<>$]/", "-", $producto["Articulo"]);
            if (!in_array($textoFamilia, $categorias) || !in_array($textoSubfamilia, $subcategorias)){
                $arrayCategoriasBBDD = Category::getSimpleCategories(Configuration::get('PS_LANG_DEFAULT'));
                if (!$this->inArrayStruct($arrayCategoriasBBDD, "name", $textoFamilia)){
                    $category = new Category();
                    $category->name = $this->createMultilanguageField($textoFamilia);
                    $category->link_rewrite = $this->createMultilanguageField(Tools::link_rewrite($textoFamilia));
                    $category->active = 1;
                    $category->id_parent = Category::getRootCategory()->id;
                    $category->save();
                }
                $arrayCategoriasBBDD = Category::getSimpleCategories(Configuration::get('PS_LANG_DEFAULT'));
				$out = Category::getRootCategory()->id;
				if (!$this->inArrayStruct($arrayCategoriasBBDD, "name", $textoSubfamilia)){
                    if ($this->inArrayStructOut($arrayCategoriasBBDD, "name", $textoFamilia, "id_category", $out)){
                        $category = new Category();
                        $category->name = $this->createMultilanguageField($textoSubfamilia);
                        $category->link_rewrite = $this->createMultilanguageField(Tools::link_rewrite($textoSubfamilia));
                        $category->active = 1;
                        $category->id_parent = (int)$out;
                        $category->save();
                    }
                }
                array_push($categorias, $textoFamilia);
                array_push($subcategorias, $textoSubfamilia);
            }
        }
    }

    /**
     * @param $productos
     * @throws PrestaShopException
     */
    private function generateManufacturers($productos)
    {
        $marcas = array();
        //obtener marcas
        foreach ($productos as $producto){
            if (!in_array($producto["Marca"], $marcas)){
                $id = Manufacturer::getIdByName($producto["Marca"]);
                if ($id <= 0){
                    $manufacture = new Manufacturer();
                    $manufacture->name = $producto["Marca"];
                    $manufacture->active = 1;
                    $manufacture->save();
                }
                array_push($marcas, $producto["Marca"]);
            }
        }
    }

    /**
     * @param $product_id
     * @param $new_images
     * @param $deletepreviousimage
     * @throws PrestaShopException
     */
    private function importImages($product_id, $new_images, $deletepreviousimage) {
        //delete all images in products
        if ($deletepreviousimage == 0){
            $this->escribirLog("--> Borramos imagenes previas");
            foreach (Image::getImages(Configuration::get('PS_LANG_DEFAULT'), $product_id) as $img){
                $img_obj = new Image($img["id_image"]);
                $img_obj->deleteImage(true);
                $img_obj->delete();
            }
        }

        //add new images
        foreach ($new_images as $img_url) {
            if (empty($img_url["Fichero"])) {
                continue;
            }
            $cover = ($img_url["Defecto"] == "1"? true: false);

            $this->escribirLog("--> Añadimos imagen: ".$img_url["Fichero"]);

            $this->copyImg($product_id, (string)$img_url["Fichero"], true, false, $cover);
        }
    }

    /**
     * @param $id_entity
     * @param string $url
     * @param bool $regenerate
     * @param bool $thumb
     * @param bool $cover
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function copyImg($id_entity, $url = '', $regenerate = true, $thumb=false, $cover=false)
    {
        $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');
        $watermark_types = explode(',', Configuration::get('WATERMARK_TYPES'));

        if (is_file(_PS_TMP_IMG_DIR_.'product_mini_'.(int)$id_entity.'.jpg')) {
            @unlink(_PS_TMP_IMG_DIR_.'product_mini_'.(int)$id_entity.'.jpg');
        }
        if (is_file(_PS_TMP_IMG_DIR_.'product_mini_'.(int)$id_entity.'_'.(int)Context::getContext()->shop->id.'.jpg')) {
            @unlink(_PS_TMP_IMG_DIR_.'product_mini_'.(int)$id_entity.'_'.(int)Context::getContext()->shop->id.'.jpg');
        }
        $image_obj = new Image();
        $image_obj->id_product = $id_entity;
        $image_obj->cover = $cover;

        if ($image_obj->save()) {
            $path = $image_obj->getPathForCreation();
            if (Tools::copy($url, $tmpfile)) {
                //Evaluate the memory required to resize the image: if it's too much, you can't resize it.
                if (!ImageManager::checkImageMemoryLimit($tmpfile)) {
                    @unlink($tmpfile);
                    return false;
                }
                $tgt_width = $tgt_height = 0;
                $src_width = $src_height = 0;
                $error = 0;
                if(file_exists($path.'.jpg') && !$thumb)
                    @unlink($path.'.jpg');
                if($thumb)
                    ImageManager::resize($tmpfile, $path.'_thumb.jpg', null, null, 'jpg', false, $error, $tgt_width, $tgt_height, 5, $src_width, $src_height);
                else
                    ImageManager::resize($tmpfile, $path.'.jpg', null, null, 'jpg', false, $error, $tgt_width, $tgt_height, 5, $src_width, $src_height);
                $images_types = ImageType::getImagesTypes("products", true);
                if ($regenerate) {
                    foreach ($images_types as $image_type) {
                        $formatted_small = ImageType::getFormattedName('small');
                        if(($thumb && $formatted_small!=$image_type['name']))
                            continue;
                        if(file_exists($path.'-'.Tools::stripslashes($image_type['name']).'.jpg'))
                            @unlink($path.'-'.Tools::stripslashes($image_type['name']).'.jpg');

                        if (ImageManager::resize(
                            $tmpfile,
                            $path.'-'.Tools::stripslashes($image_type['name']).'.jpg',
                            $image_type['width'],
                            $image_type['height'],
                            'jpg',
                            false,
                            $error,
                            $tgt_width,
                            $tgt_height,
                            5,
                            $src_width,
                            $src_height
                        )) {
                            if (is_file(_PS_TMP_IMG_DIR_.'product_mini_'.(int)$id_entity.'.jpg')) {
                                @unlink(_PS_TMP_IMG_DIR_.'product_mini_'.(int)$id_entity.'.jpg');
                            }
                            if (is_file(_PS_TMP_IMG_DIR_.'product_mini_'.(int)$id_entity.'_'.(int)Context::getContext()->shop->id.'.jpg')) {
                                @unlink(_PS_TMP_IMG_DIR_.'product_mini_'.(int)$id_entity.'_'.(int)Context::getContext()->shop->id.'.jpg');
                            }
                        }
                        if (in_array($image_type['id_image_type'], $watermark_types)) {
                            Hook::exec('actionWatermark', array('id_image' => $image_obj->id, 'id_product' => $id_entity));
                        }
                    }
                }
                @unlink($tmpfile);
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * @param $array
     * @param $field
     * @param $value
     * @return bool
     */
    private function inArrayStruct($array, $field, $value){
        foreach ($array as $a){
            if ($a[$field] == $value)
                return true;
        }
        return false;
    }
	
	private function inArrayStructOut($array, $field, $value, $fieldout, &$out){
        foreach ($array as $a){
            if ($a[$field] == $value) {
                $out = $a[$fieldout];
                return true;
            }
        }
        return false;
    }

    /**
     * @param $familia
     * @return int
     */
    private function searchCategory($familia, $subcategory)
    {
		$cate = array();
        $arrayCategoriasBBDD = Category::getSimpleCategories(Configuration::get('PS_LANG_DEFAULT'));
        foreach ($arrayCategoriasBBDD as $cat){
            if ($cat["name"] == $familia || $cat["name"] == $subcategory)
                array_push($cate, $cat["id_category"]);
        }
        return $cate;
    }

    /**
     * @param $field
     * @return array
     */
    private function createMultilanguageField($field) {
        $languages = Language::getLanguages();
        $res = array();

        foreach ($languages as $lang) {
            $res[$lang['id_lang']] = preg_replace("/[\/\&%#\<>$]/", "-", $field);
        }

        return $res;
    }

    /**
     * @param $urlFeed
     * @return array
     * @throws Exception
     */
    private function parseProducts($urlFeed){
        $ret = array();

        if (file_exists($urlFeed."crvnet_export.xml")){
            $this->escribirLog("--> Abrimos XML: ".$urlFeed."crvnet_export.xml");
            $xml = new DOMDocument();
            $xml->preserveWhiteSpace = false;
            $xml->load($urlFeed."crvnet_export.xml");

            $feedXML = $xml->getElementsByTagName('PiezaExp');
            $this->escribirLog("--> Importamos ".count($feedXML)." elementos.");
            foreach ($feedXML as $feed) {
                $product = array(
                    'Familia'       => $feed->getElementsByTagName('Familia')->item(0)->childNodes->item(0)->nodeValue,
                    'Articulo'     => $feed->getElementsByTagName('Articulo')->item(0)->childNodes->item(0)->nodeValue,
                    'Marca'      => $feed->getElementsByTagName('Marca')->item(0)->childNodes->item(0)->nodeValue,
                    'Modelo'     => array (
                        "Descripcion" => $feed->getElementsByTagName('Modelo')->item(0)->childNodes->item(0)->nodeValue,
                        "Inicio" => $feed->getElementsByTagName('Modelo')->item(0)->childNodes->item(1)->nodeValue,
                        "Fin" => $feed->getElementsByTagName('Modelo')->item(0)->childNodes->item(2)->nodeValue
                    ),
                    'Version' => $feed->getElementsByTagName('Version')->item(0)->childNodes->item(0)->nodeValue,
                    'RefId' => $feed->getAttribute('RefId'),
                    'RefPieza' => $feed->getAttribute('RefPieza'),
                    'Precio' => (float)$feed->getAttribute('Precio'),
                    'Peso' => (float)$feed->getAttribute('Peso'),
                    'Puertas' => $feed->getAttribute('Puertas'),
                    'Observaciones' => $feed->getAttribute('Observaciones'),
                    'Imagenes'     => $this->parseImagenes($feed->getElementsByTagName('ImagenExp'),$urlFeed)
                );
                if (trim($product["Familia"]) != "" && trim($product["Articulo"]) != "" && strtolower($feed->getAttribute('Atributo2')) != "x"){
                    $this->escribirLog("--> Producto ".serialize($product)." parseado OK.");
                    array_push($ret, $product);
                }else{
                    $this->escribirLog("--> Producto ".serialize($product)." no parseado por no cumplir condiciones.");
                }
            }

            return $ret;
        }else{
            throw new Exception("No existe el fichero ".$urlFeed."crvnet_export.xml");
        }
    }

    /**
     * @param $feedImagenes
     * @param $urlFeed
     * @return array
     */
    private function parseImagenes($feedImagenes, $urlFeed){
        $ret = array();
        foreach ($feedImagenes as $feed) {
            $dato = array("Fichero" => $urlFeed."/images/".$feed->getAttribute('Fichero'), "Defecto" => $feed->getAttribute('Defecto'));
            $this->escribirLog("----> Importamos imágenes ".serialize($dato));
            array_push($ret , $dato);
        }
        return $ret;
    }
}