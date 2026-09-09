<?php
session_start();
ob_start();  // Start output buffering to prevent unwanted output before PDF header

// Ensure that the correct path is provided for TCPDF
require_once('../admin/TCPDF-6.8.2/TCPDF-6.8.2/tcpdf.php');  // Update path accordingly
require_once dirname(__DIR__) . '/config.php';

// Check if TCPDF class is loaded properly
if (!class_exists('TCPDF')) {
    die('TCPDF library not found.');
}

$conn = get_db_connection();


// Get student LRN from URL
$lrn = isset($_GET['lrn']) ? $_GET['lrn'] : die("No LRN provided.");

// Use prepared statement to fetch student details
$sql = "SELECT lrn, first_name, last_name, middle_name, dob, gender, grade, track, strand, section, school_year,
        barangay, municipality, province, street, house_number, zip_code,
        permanent_street, permanent_barangay, permanent_municipality, permanent_province, permanent_zip_code,
        disability, ip_community, FourPs, indigenous, mother_tongue, contact, semester,
        father_last_name, father_first_name, father_middle_name,
        mother_last_name, mother_first_name, mother_middle_name,
        guardian_last_name, guardian_first_name, guardian_middle_name, contact_guardian_parent,
        is_returning_or_transfer, last_grade_level, last_school_year, previous_school, previous_school_id
        FROM students WHERE lrn = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $lrn);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    exit("Student not found.");
}

if (!isset($_SESSION['registrar'])) {
    header("Location: ../admin/login.html");
    exit();
}


// Create PDF object
$pdf = new TCPDF();
// Check if PDF object is created properly before proceeding
if (!$pdf) {
    die("Error creating PDF object.");
}

$pdf->SetAutoPageBreak(TRUE, 10);
$pdf->AddPage('P', array(215, 345)); // Set custom page size (width: 215mm, height: 345mm)

// Draw a border (x, y, width, height)
$pdf->Rect(5, 5, $pdf->getPageWidth() - 10, $pdf->getPageHeight() - 10);



// Add logos to left and right
$leftLogo = 'logo.png'; // Replace with your actual left logo path
$rightLogo = 'LOGO STA. CRUZ.png'; // Replace with your actual right logo path  

// Check if logo paths are correct before attempting to add them
if (file_exists($leftLogo)) {
    $pdf->Image($leftLogo, 35, 12,15);  // Left logo at position (10, 5) with width of 40
} else {
    die("Left logo not found.");
}

if (file_exists($rightLogo)) {
    $pdf->Image($rightLogo, 160, 12, 15); // Right logo at position (160, 5) with width of 40
} else {
    die("Right logo not found.");
}

// Title
$pdf->SetFont('Helvetica', 'B', 12);

$pdf->Cell(0, 5, "BASIC EDUCATION ENROLLMENT FORM", 0, 1, 'C');
$pdf->SetFont('Helvetica', '', 10);
// School Details for Official Use
$pdf->SetFont('Helvetica', '', 10);
$pdf->Cell(0, 3, "Santa Cruz National Senior High School", 0, 1, 'C');
$pdf->Cell(0, 3, "Sta. Cruz, Davao del Sur", 0, 1, 'C');

$pdf->Ln(5);// Space before student information section
// Student Information Section
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->Cell(187, 5, "LEARNER INFORMATION", 1, 1, 'L');

$pdf->SetFont('Helvetica', '', 8);
$pdf->Cell(60, 5, "Learner Reference No. (LRN):", 0,0);
$pdf->Cell(20, 5, $student['lrn'], 0,1,'R');

$pdf->Cell(60, 5, "Last Name:", 0,0);
$pdf->Cell(20, 5, $student['last_name'], 0,1);

$pdf->Cell(60, 5, "First Name:", 0,0);
$pdf->Cell(30, 5, $student['first_name'], 0,1);

$pdf->Cell(60, 5, "Middle Name:", 0,0);
$pdf->Cell(30, 5, isset($student['middle_name']) ? $student['middle_name'] : 'N/A', 0,1);

$pdf->Cell(60, 5, "Birthdate:", 0,0);
$pdf->Cell(30, 5, $student['dob'], 0,1);

$pdf->Cell(60, 5, "Sex:", 0,0);
$pdf->Cell(30, 5, $student['gender'], 0,1);

$pdf->Cell(60,5, "Is the child a Learner with Disability?:", 0,0);
$pdf->Cell(30, 5, isset($student['disability']) ? $student['disability'] : 'N/A', 0,1);

$pdf->Cell(60, 5, "Is the child  an indigenous person?:", 0,0);
$pdf->Cell(30, 5, isset($student['indigenous']) ? $student['indigenous'] : 'N/A', 0,1);

$pdf->Cell(60, 5, "Is your family a beneficiary of 4Ps?:", 0,0);
$pdf->Cell(30, 5, isset($student['FourPs']) ? $student['FourPs'] : 'N/A', 0,1);

$pdf->Cell(60, 5, "Grade Level to Enroll:", 0,0);
$pdf->Cell(30, 5, $student['grade'], 0,1);

$pdf->Cell(60, 5, "Track:", 0,0);           
$pdf->Cell(30, 5, $student['track'], 0,1);

$pdf->Cell( 60, 5, "Strand:", 0,0);
$pdf->Cell(30,5,$student['strand'], 0,1);

$pdf->Cell (60, 5, "Semester:", 0,0);
$pdf->Cell(30, 5, isset($student['semester']) ? $student['semester'] : 'N/A', 0,1);

$pdf->Cell( 60, 5, "Contact No.:", 0,0);
$pdf->Cell(30, 5, $student['contact'], 0,1);                                                                                                                                                                                                                                                                                       

$pdf->Cell(60, 5, "School Year:", 0,0);
$pdf->Cell(30, 5, $student['school_year'], 0,1);

$pdf->Ln(5);
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->Cell(187, 5, "Is  the child a  Learner with  Disability? [ ] Yes [ ] No", 1,0, 'C'); 

$pdf->Ln(5);
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->Cell(60, 5, "If Yes, specify the type of disability:", 0,0);

$pdf->Ln(5);
$pdf->Cell(60, 5, "[ ] Visual Impairment        [ ] Hearing Impairment                       [ ] Learning  Disability                           [ ] Intellectual Disability", 0,1);
$pdf->Cell(60, 5, "[ ] a. Blind                          [ ] Autism Spectrum Disorder           [ ] Emotional- Behavioral Disorder      [ ] Orthopedic/Physical Handicap ", 0, 1);
$pdf->Cell(60, 5, "[ ] b.Low Vision                 [ ] Speech/Language Disorder          [ ] Cerebral Palsy                                   [ ] Special Health  Problem/ Chronic Disease", 0, 1);
$pdf->Cell(60, 5, "[ ] Multiple Disorder                                                                                                                                       [ ] a. Cancer ", 0,1);



$pdf->Ln(5);
// Parent/Guardian Information Section
$pdf->SetFont('Helvetica', 'B', 8   );
$pdf->Cell(187, 5, "PARENT/GUARDIAN INFORMATION", 1, 1, 'L');

$pdf->SetFont('Helvetica', '', 8);
$pdf->Cell(60, 5, "Father's Last Name:", 0, 0);
$pdf->Cell(20, 5,  $student['father_last_name'],0, 1);

$pdf->Cell(60, 5, "Father's First Name:", 0, 0);    
 $pdf->Cell(30, 5, $student['father_first_name'], 0, 1);
 $pdf->Cell(60, 5, "Father's Middle Name:", 0, 0);
 $pdf->Cell(30, 5, isset($student['father_middle_name']) ? $student['father_middle_name'] : 'N/A', 0, 1);

 $pdf->Cell(60, 5, "Mother's First Name:", 0, 0);
$pdf->Cell(30, 5, isset($student['mother_first_name']) ? $student['mother_first_name'] : 'N/A', 0, 1);

$pdf->Cell(60, 5, "Mother's Last Name:", 0, 0);
$pdf->Cell(30, 5, isset($student['mother_last_name']) ? $student['mother_last_name'] : 'N/A', 0, 1); 

$pdf->Cell(60, 5, "Mother's Middle Name:", 0, 0);
$pdf->Cell( 30, 5, isset($student['mother_middle_name']) ? $student['mother_middle_name'] : 'N/A', 0, 1);

$pdf->Cell(60, 5, "Guardian's Last Name", 0, 0);
$pdf->Cell(30, 5, isset($student['guardian_last_name']) ? $student['guardian_last_name'] : 'N/A', 0, 1);

$pdf->Cell(60, 5, "Guardian's First Name:", 0, 0);
$pdf->Cell(30, 5, isset($student['guardian_first_name']) ? $student['guardian_first_name'] : 'N/A', 0, 1);

$pdf->Cell(60, 5, "Guardian's Middle Name:", 0, 0);
$pdf->Cell(30, 5, isset($student['guardian_middle_name']) ? $student['guardian_middle_name'] : 'N/A', 0, 1);

$pdf->Cell(60, 5, "Guardian/Parent's Phone:", 0, 0);
$pdf->Cell(50, 5, isset($student['contact_guardian_parent']) ? $student['contact_guardian_parent'] : 'N/A', 0, 1);

$pdf->Ln(5);

// Current Address Section
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->Cell(187, 5, "CURRENT ADDRESS", 1, 1, 'L');

$pdf->SetFont('Helvetica', '', 8);
$pdf->Cell(60, 5,"Street/Purok:", 0, 0);
$pdf->Cell(30, 5, isset($student['street']) ? $student['street'] : 'N/A', 0, 1);

$pdf->Cell(60, 5, "Barangay:", 0, 0);
$pdf->Cell(30, 5, isset($student['barangay']) ? $student['barangay'] : 'N/A', 0, 1);

$pdf->Cell(60, 5, "Municipality/City:", 0, 0);
$pdf->Cell(30, 5, isset($student['municipality']) ? $student['municipality'] : 'N/A', 0, 1);

$pdf->Cell(60, 5, "Province:", 0, 0);
$pdf->Cell(30, 5, isset($student['province']) ? $student['province'] : 'N/A', 0, 1);

$pdf->Cell(60, 5,  "Zip Code:", 0, 0);
$pdf->Cell(30, 5, isset($student['zip_code']) ? $student['zip_code'] : 'N/A', 0, 1);

$pdf->Ln(5);

// Permanent Address Section
$pdf->setFont('Helvetica', 'B',8);
$pdf->Cell(187, 5, "PERMANENT ADDRESS", 1, 1, 'L');

$pdf->SetFont('Helvetica', '', 8);      

$pdf->Cell(60, 5, "Permanent Street/Purok:", 0, 0);
$pdf->Cell(30, 5, isset($student['permanent_street']) ? $student['permanent_street'] : 'N/A', 0, 1);

$pdf->Cell(60, 5, "Permanent Barangay:", 0, 0);
$pdf->Cell(30, 5, isset($student['permanent_barangay']) ? $student['permanent_barangay'] : 'N/A', 0, 1);

$pdf->Cell(60, 5, "Permanent Municipality/City:", 0, 0);
$pdf->Cell(30, 5, isset($student['permanent_municipality']) ? $student['permanent_municipality'] : 'N/A', 0, 1);

$pdf->Cell(60, 5, "Permanent Province:", 0, 0);
$pdf->Cell(30, 5, isset($student['permanent_province']) ? $student['permanent_province'] : 'N/A', 0, 1);

$pdf->Cell(60, 5, "Permanent Zip Code:", 0, 0);
$pdf->Cell(30, 5, isset($student['permanent_zip_code']) ? $student['permanent_zip_code'] : 'N/A', 0, 1);

$pdf->Ln(60); // Space before signature section

// Previous School Year Information Section
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->Cell(191, 5, "LAST SCHOOL YEAR (RETURNING LEARNER)
:", 1, 1, 'L');

$pdf->SetFont('Helvetica', '', 8);
$pdf->Cell(60, 3, "Last School Year:", 0,0);
$pdf->Cell(30, 3, $student['last_school_year'], 0, 1);

$pdf->Cell(60, 5, "Last Grade Level:", 0, 0);
$pdf->Cell(30, 5, $student['last_grade_level'], 0, 1);

$pdf->Cell(60, 5, "Previous School:", 0, 0);
$pdf->Cell(30, 5, isset($student['previous_school']) ? $student['previous_school'] : 'N/A', 0, 1);

$pdf->Ln(10); // Space before signature section

$pdf->SetFont('Helvetica', 'B', 8);
$pdf->Cell(187, 5, "If school will implement other distance learning modalities aside from face-to-face instruction, what would you prefer for your child?", 1, 1, 'C');

$pdf->SetFont('Helvetica', '', 8);
$pdf->Cell(187, 5, "Other Distance Learning modalities: " , 0,1, 'L');
$pdf->Cell(187, 8, isset($student['learning_modality']) ? $student['learning_modality'] : 'N/A', 0,1, 'L');


$pdf->SetFont('Helvetica', 'B', 8);
$pdf->Cell(60,3, "Choose all that apply:", 0, 1, 'L');
$pdf->Cell(60, 3, "[ ] Modular (Print)            [ ] Online                                      [ ] Radio-Based Instruction                               [ ] Blended", 0, 1, 'L');
$pdf->Cell(60, 3, "[ ] Modular (Digital)         [ ] Educational Television           [ ] Homeschooling", 0, 1, 'L');
$pdf->Ln(20); // Space before signature section

// Parent/Student Signature Section
$pdf->SetFont('Helvetica', 'B', 8);
$pdf->Cell(191, 8, "PARENT/STUDENT SIGNATURE", 0, 1, 'C');
$pdf->Ln(5); // Space before signatures

$pdf->Ln(5 ); // Space before footer
$pdf->SetFont('Helvetica', 'I', 8);
$pdf->Cell(187, 0, "I hereby certify that the above information given are true and correct o the best of my knowledge and I allow the",0,1, 'C');
$pdf->Cell(187, 0, "Department of Education to use my child’s details to create and/or update his/her learner profile in the Learner Information System.", 0, 1, 'C');
$pdf->Cell(187, 0, "The information herein shall be treated as confidential in compliance with the Data Privacy Act of 2012.", 0, 1, 'C');

$pdf->Ln(20 ); // Space before signature lines

$pdf->SetFont('Helvetica', '', 8);
// Parent/Guardian Signature
$pdf->Cell(95, 5, "__________________________________", 0, 0, 'C'); // First signature line
$pdf->Cell(95, 5, "__________________________________", 0, 1, 'C'); // Second signature line (Student)

// Labels
$pdf->Cell(95, 5, "Parent/Guardian's Signature", 0, 0, 'C');
$pdf->Cell(95, 5, "Student's Signature", 0, 1, 'C');

$pdf->Ln(5); // Space before the linew

ob_end_clean();  // Clean the output buffer before sending the PDF
// Draw a cutting line
$pdf->Ln( 50 );// Space // Add space before the line

$pdf->SetFont('Helvetica', '', 12);
$pdf->Ln(5); // Add space after the line
$pageHeight = $pdf->getPageHeight();
$bottomMargin = 50; // Adjust this value as needed
$yPosition = $pageHeight - $bottomMargin;
// Set Y position
$pdf->SetY($yPosition);

$pdf->Cell(0, 3, str_repeat('-', 180), 0, 1, 'C');
// Use actual section from student record instead of hardcoded logic
$section = isset($student['section']) && !empty($student['section']) ? $student['section'] : 'Unassigned';



// Certification Text
$pdf->SetFont('Helvetica', 'B', 6);
$pdf->Cell(0, 1,"CERTIFICATION OF ENROLLMENT", 0, 1, 'C');

$pdf->SetFont('Helvetica', '', 6);
$pdf->MultiCell(0, 0, "This certifies that the learner listed below is officially enrolled at Santa Cruz National Senior High School for the current school year:\n\n", 0, 'C');

$pdf->SetFont('Helvetica', 'B', 6);// Bold font for labels
$pdf->MultiCell(0, 0,
    "LRN: " . (isset($student['lrn']) ? $student['lrn'] : 'N/A') . "\n" .
    "Name: " . (isset($student['first_name']) ? $student['first_name'] : 'N/A') . " " . (isset($student['middle_name']) ? $student['middle_name'] : '') . " " . (isset($student['last_name']) ? $student['last_name'] : 'N/A') . "\n" .
    "Track & Strand: " . (isset($student['track']) ? $student['track'] : 'N/A') . " - " . (isset($student['strand']) ? $student['strand'] : 'N/A') . "\n" .
    "Assigned Section: " . $section,
0, 'C');

// Date
$currentDate = date('F j, Y');
$pdf->Ln(1);
$pdf->Cell(0, 0,"Issued on: " . $currentDate, 0, 1, 'C');

$pdf->Ln(2); // Space before signature section

$pdf->SetFont('Helvetica', 'B', 5);
// Signature Section
$pdf->Cell(0, 0, "_________________________", 0, 1, 'C');
$pdf->Cell(0, 2, "Registrar/Admin Signature", 0, 1, 'C');

$pdf->Output();
exit(); // Exit the script after generating the PDF

?>