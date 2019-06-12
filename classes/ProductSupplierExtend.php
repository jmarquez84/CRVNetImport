<?php
/**
 * Created by PhpStorm.
 * User: Jorge
 * Date: 12/11/2018
 * Time: 18:15
 */

class ProductSupplierExtend extends ProductSupplier
{
    public static function getIdProductFromReference($product_supplier_reference, $id_supplier)
    {
        // build query
        $query = new DbQuery();
        $query->select('ps.id_product');
        $query->from('product_supplier', 'ps');
        $query->where("ps.product_supplier_reference = '".$product_supplier_reference."' AND ps.id_supplier = ".$id_supplier);

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }
}