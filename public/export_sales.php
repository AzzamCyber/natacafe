<?php
// =========================================================
// PUBLIC/EXPORT_SALES.PHP - XML SPREADSHEET 2003 EXPORT (PROFESSIONAL TABLE)
// =========================================================

// --- Configuration & Security Setup ---
require_once __DIR__ . '/../config/config.php';
require_once dirname(__DIR__) . '/src/Services/UploadService.php'; 
require_once dirname(__DIR__) . '/config/struck.php'; 

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Re-defining essential global functions
function audit_log($pdo, $action, $target_table, $target_id = null) {
    $userId = $_SESSION['user_id'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, target_table, target_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $target_table, $target_id]);
}

function require_role($required_role) {
    $user_role = $_SESSION['role'] ?? null;
    if ($user_role !== $required_role && $user_role !== 'admin') { 
        header('Location: index.php?page=admin_dashboard&error=Unauthorized Access');
        exit;
    }
}

$pdo = get_pdo_connection();
require_role('admin');

// -----------------------------------------------------------------
// --- Export Logic ---
// -----------------------------------------------------------------

// 1. Ambil Data Penjualan Rinci
$query = "
    SELECT
        o.order_number, 
        o.created_at, 
        u.name AS cashier_name, 
        o.customer_name, 
        o.total_amount, 
        o.payment_method, 
        o.order_status, 
        oi.product_name, 
        oi.quantity, 
        oi.price_per_item
    FROM orders o
    LEFT JOIN users u ON o.cashier_id = u.id
    JOIN order_items oi ON o.id = oi.order_id
    ORDER BY o.created_at DESC
";
$stmt = $pdo->query($query);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($results)) {
    header('Location: index.php?page=admin_dashboard&error=' . urlencode('Tidak ada data penjualan untuk diexport.'));
    exit;
}

// 2. Set Header untuk Download XML Spreadsheet (ekstensi .xls)
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename=Laporan_Penjualan_' . date('Ymd_His') . '.xls');

// 3. Mulai Output XML
$output = '<?xml version="1.0"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <Author>Natakenshi System</Author>
  <Created>' . date('Y-m-d\TH:i:s\Z') . '</Created>
  <LastSaved>' . date('Y-m-d\TH:i:s\Z') . '</LastSaved>
  <Company>' . RECEIPT_STORE_NAME . '</Company>
  <Version>16.00</Version>
 </DocumentProperties>
 <OfficeDocumentSettings xmlns="urn:schemas-microsoft-com:office:office">
  <AllowPNG/>
 </OfficeDocumentSettings>
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Bottom"/>
   <Borders/>
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#000000"/>
   <Interior/>
   <NumberFormat/>
   <Protection/>
  </Style>
  
  <Style ss:ID="sHeader">
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#FFFFFF" ss:Bold="1"/>
   <Interior ss:Color="#0288D1" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
  </Style>
  
  <Style ss:ID="sDateTime">
   <NumberFormat ss:Format="yyyy\-mm\-dd\ hh:mm:ss"/>
  </Style>
  
  <Style ss:ID="sCurrency">
   <NumberFormat ss:Format="#,##0"/>
  </Style>

 </Styles>
 <Worksheet ss:Name="Laporan Penjualan">
  <Table>
   <Column ss:Index="1" ss:AutoFitWidth="0" ss:Width="100"/>
   <Column ss:Index="2" ss:AutoFitWidth="0" ss:Width="120"/>
   <Column ss:Index="5" ss:AutoFitWidth="0" ss:Width="80"/>

   <Row ss:StyleID="sHeader">
    <Cell><Data ss:Type="String">NOMOR_ORDER</Data></Cell>
    <Cell><Data ss:Type="String">TANGGAL_WAKTU</Data></Cell>
    <Cell><Data ss:Type="String">KASIR_PENCATAT</Data></Cell>
    <Cell><Data ss:Type="String">NAMA_PELANGGAN</Data></Cell>
    <Cell><Data ss:Type="String">TOTAL_PENJUALAN_RP</Data></Cell>
    <Cell><Data ss:Type="String">METODE_BAYAR</Data></Cell>
    <Cell><Data ss:Type="String">STATUS_ORDER</Data></Cell>
    <Cell><Data ss:Type="String">NAMA_PRODUK</Data></Cell>
    <Cell><Data ss:Type="String">QTY</Data></Cell>
    <Cell><Data ss:Type="String">HARGA_SATUAN</Data></Cell>
   </Row>';

// 4. Masukkan Data ke XML
foreach ($results as $row) {
    // Memformat tanggal ke format ISO untuk dikenali Excel
    $datetime_excel = date('Y-m-d\TH:i:s.000', strtotime($row['created_at']));
    
    $output .= '<Row>';
    $output .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['order_number']) . '</Data></Cell>';
    
    // Cell untuk Tanggal, menggunakan StyleID sDateTime untuk format dan Data Type DateTime
    $output .= '<Cell ss:StyleID="sDateTime"><Data ss:Type="DateTime">' . htmlspecialchars($datetime_excel) . '</Data></Cell>';
    
    $output .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['cashier_name']) . '</Data></Cell>';
    $output .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['customer_name']) . '</Data></Cell>';
    
    // Cell untuk Uang, menggunakan StyleID sCurrency
    $output .= '<Cell ss:StyleID="sCurrency"><Data ss:Type="Number">' . ((float) $row['total_amount']) . '</Data></Cell>';
    
    $output .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['payment_method']) . '</Data></Cell>';
    $output .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['order_status']) . '</Data></Cell>';
    $output .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['product_name']) . '</Data></Cell>';
    $output .= '<Cell><Data ss:Type="Number">' . ((int) $row['quantity']) . '</Data></Cell>';
    $output .= '<Cell ss:StyleID="sCurrency"><Data ss:Type="Number">' . ((float) $row['price_per_item']) . '</Data></Cell>';
    $output .= '</Row>';
}

// 5. Tutup XML
$output .= '
  </Table>
 </Worksheet>
</Workbook>';

echo $output;

audit_log($pdo, "Exported sales data to XML Spreadsheet 2003 (.xls)", 'orders');

exit;
?>