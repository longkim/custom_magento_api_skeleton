<?php
$installer = $this;
$installer->startSetup();
if (!Mage::helper('core')->isModuleEnabled('Towersystems_TieredShipping')){
	$installer->getConnection()->addColumn($installer->getTable('retailer/split_order'),'xero_id',array('type' => Varien_Db_Ddl_Table::TYPE_TEXT,'comment' =>'xero id'));
	$installer->getConnection()->addColumn($installer->getTable('retailer/split_order'),'xero_message',array('type' => Varien_Db_Ddl_Table::TYPE_TEXT,'comment' =>'xero message'));
	$installer->getConnection()->addColumn($installer->getTable('retailer/split_order'),'xero_response',array('type' => Varien_Db_Ddl_Table::TYPE_TEXT,'comment' =>'xero response'));
	$installer->getConnection()->addColumn($installer->getTable('retailer/split_order'),'xero_data',array('type' => Varien_Db_Ddl_Table::TYPE_TEXT,'comment' =>'xero data'));
	$installer->getConnection()->addColumn($installer->getTable('retailer/split_order'),'xero_status',array('type' => Varien_Db_Ddl_Table::TYPE_BOOLEAN,'comment' =>'xero status'));
	$installer->getConnection()->addColumn($installer->getTable('sales/order'),'xero_id',array('type' => Varien_Db_Ddl_Table::TYPE_TEXT,'comment' =>'xero id'));
	$installer->getConnection()->addColumn($installer->getTable('sales/order'),'xero_message',array('type' => Varien_Db_Ddl_Table::TYPE_TEXT,'comment' =>'xero message'));
	$installer->getConnection()->addColumn($installer->getTable('sales/order'),'xero_response',array('type' => Varien_Db_Ddl_Table::TYPE_TEXT,'comment' =>'xero response'));
	$installer->getConnection()->addColumn($installer->getTable('sales/order'),'xero_data',array('type' => Varien_Db_Ddl_Table::TYPE_TEXT,'comment' =>'xero data'));
	$installer->getConnection()->addColumn($installer->getTable('sales/order'),'xero_status',array('type' => Varien_Db_Ddl_Table::TYPE_BOOLEAN,'comment' =>'xero status'));
}else{
	$installer->getConnection()->addColumn($installer->getTable('sales/order'),'xero_id',array('type' => Varien_Db_Ddl_Table::TYPE_TEXT,'comment' =>'xero id'));
	$installer->getConnection()->addColumn($installer->getTable('sales/order'),'xero_message',array('type' => Varien_Db_Ddl_Table::TYPE_TEXT,'comment' =>'xero message'));
	$installer->getConnection()->addColumn($installer->getTable('sales/order'),'xero_response',array('type' => Varien_Db_Ddl_Table::TYPE_TEXT,'comment' =>'xero response'));
	$installer->getConnection()->addColumn($installer->getTable('sales/order'),'xero_data',array('type' => Varien_Db_Ddl_Table::TYPE_TEXT,'comment' =>'xero data'));
	$installer->getConnection()->addColumn($installer->getTable('sales/order'),'xero_status',array('type' => Varien_Db_Ddl_Table::TYPE_BOOLEAN,'comment' =>'xero status'));
	$installer->getConnection()->addColumn($installer->getTable('sales/order'),'xero_bill_id',array('type' => Varien_Db_Ddl_Table::TYPE_TEXT,'comment' =>'xero bill id'));
	$installer->getConnection()->addColumn($installer->getTable('sales/order'),'xero_bill_message',array('type' => Varien_Db_Ddl_Table::TYPE_TEXT,'comment' =>'xero bill message'));
	$installer->getConnection()->addColumn($installer->getTable('sales/order'),'xero_bill_response',array('type' => Varien_Db_Ddl_Table::TYPE_TEXT,'comment' =>'xero bill response'));
	$installer->getConnection()->addColumn($installer->getTable('sales/order'),'xero_bill_data',array('type' => Varien_Db_Ddl_Table::TYPE_TEXT,'comment' =>'xero bill data'));
	$installer->getConnection()->addColumn($installer->getTable('sales/order'),'xero_bill_status',array('type' => Varien_Db_Ddl_Table::TYPE_BOOLEAN,'length' =>'1','comment' =>'xero bill status'));
}


$installer->endSetup();


?>