<?php
require_once 'config/db.php';
$filter_supplier = $_GET['supplier_id'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT p.*, s.name AS supplier_name FROM parts_inventory p LEFT JOIN suppliers s ON p.supplier_id=s.id WHERE 1=1";
$params = [];
if ($filter_supplier !== '') { $sql .= " AND p.supplier_id = ?"; $params[] = $filter_supplier; }
if ($filter_status !== '') {
  if($filter_status==='out') $sql.=" AND p.quantity <= 0";
  elseif($filter_status==='low') $sql.=" AND p.quantity <= p.reorder_level";
}
if($search!=='') { $sql .= " AND (p.part_name LIKE ? OR p.part_number LIKE ? OR s.name LIKE ?)"; $params[]="%$search%"; $params[]="%$search%"; $params[]="%$search%"; }
$sql.=" ORDER BY p.id DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="parts_inventory_'.date('Ymd_His').'.csv"');

$out = fopen('php://output','w');
fputcsv($out, ['ID','Part Name','Part Number','Category','Quantity','Reorder Level','Unit Price','Supplier','Added On']);
foreach($rows as $r){
  fputcsv($out, [$r['id'],$r['part_name'],$r['part_number'],$r['category'],$r['quantity'],$r['reorder_level'],number_format((float)($r['unit_price']??0),2),$r['supplier_name']??'',$r['added_on']??'']);
}
fclose($out);
exit;