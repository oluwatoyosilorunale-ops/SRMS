<?php
require 'config.php';

if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student'){
    header("Location: index.php");
    exit();
}

$student_id = $_SESSION['user']['id'];

$term = $_GET['term'] ?? null;
$session = $_GET['session'] ?? null;
$term = $term ?: get_current_term();
$session = $session ?: get_current_session();

// Get student details
$stmt2 = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt2->execute([$student_id]);
$student = $stmt2->fetch();

// Create PDF object first
require('fpdf/fpdf.php');
$pdf = new FPDF();
$pdf->AddPage();

// Fetch results for the selected session/term (session optional)
$params = [$student_id, $term];
$sessionClause = '';
if($session !== ''){
    $sessionClause = ' AND r.session = ?';
    $params[] = $session;
}
$stmt = $pdo->prepare("SELECT r.*, s.name as subject FROM results r JOIN subjects s ON r.subject_id = s.id WHERE r.student_id = ? AND r.term = ?" . $sessionClause);
$stmt->execute($params);
$results = $stmt->fetchAll();

// Header - Centered
$pdf->Image('assets/images/designed2.png', 85, 10, 20); // Centered logo
$pdf->SetXY(0, 35);
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Mabest Academy',0,1,'C');
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,10,'Oke_Ijebu Road, Akure, Ondo State',0,1,'C');

// Title
$pdf->Ln(10);
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,10,'Student Report Card',0,1,'C');

// Student Details
$pdf->Ln(5);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,8,'Student Name: ' . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']),0,1,'L');
$pdf->Cell(0,8,'Student ID: ' . htmlspecialchars($student['student_id']),0,1,'L');
$pdf->Cell(0,8,'Class: ' . htmlspecialchars($student['class'] . ' ' . ($student['arm'] ?? 'A')),0,1,'L');
$pdf->Cell(0,8,'Academic Session: '.($session ?: 'N/A'),0,1,'L');
$pdf->Cell(0,8,'Term: '.($term ?: 'N/A'),0,1,'L');
$pdf->Ln(5);

// Check if results exist
if(empty($results)){
    $pdf->SetFont('Arial','',12);
    $pdf->MultiCell(0,10,"No results recorded for this session and term.",0,'L');
    $pdf->Output();
    exit;
}


$pdf->SetFont('Arial','B',12);
$pdf->Cell(30,10,'Subject',1);
$pdf->Cell(20,10,'1st Test',1);
$pdf->Cell(20,10,'2nd Test',1);
$pdf->Cell(20,10,'3rd Test',1);
$pdf->Cell(20,10,'Exam',1);
$pdf->Cell(20,10,'CA',1);
$pdf->Cell(20,10,'Total',1);
$pdf->Cell(20,10,'Grade',1);
$pdf->Ln();

$pdf->SetFont('Arial','',12);
function gradeFromPDF($total) {
    if($total >= 70) return 'A';
    if($total >= 60) return 'B';
    if($total >= 50) return 'C';
    if($total >= 45) return 'D';
    return 'F';
}
function gradeColorPDF($grade, $pdf) {
    // Set fill color for grade cell only
    switch($grade) {
        case 'A': $pdf->SetFillColor(200,247,197); $pdf->SetTextColor(32,114,69); break;
        case 'B': $pdf->SetFillColor(212,241,250); $pdf->SetTextColor(23,107,160); break;
        case 'C': $pdf->SetFillColor(255,247,192); $pdf->SetTextColor(160,138,23); break;
        case 'D': $pdf->SetFillColor(255,224,178); $pdf->SetTextColor(160,93,23); break;
        case 'F': $pdf->SetFillColor(255,214,214); $pdf->SetTextColor(160,23,23); break;
        default: $pdf->SetFillColor(255,255,255); $pdf->SetTextColor(0,0,0); break;
    }
}
foreach($results as $row){
    $ca = $row['test1'] + $row['test2'] + $row['test3'];
    $total = $ca + $row['exam'];
    $grade = gradeFromPDF($total);
    $pdf->Cell(30,10,$row['subject'],1);
    $pdf->Cell(20,10,$row['test1'],1);
    $pdf->Cell(20,10,$row['test2'],1);
    $pdf->Cell(20,10,$row['test3'],1);
    $pdf->Cell(20,10,$row['exam'],1);
    $pdf->Cell(20,10,$ca,1);
    $pdf->Cell(20,10,$total,1);
    gradeColorPDF($grade, $pdf);
    $pdf->Cell(20,10,$grade,1,0,'C',true);
    $pdf->SetTextColor(0,0,0); // Reset text color
    $pdf->Ln();
}

$pdf->Output();