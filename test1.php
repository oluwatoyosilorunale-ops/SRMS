<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            text-align: center;
            color: white;
            margin-bottom: 40px;
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        /* Student List Styles */
        .student-list {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            margin-bottom: 40px;
        }

        .list-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            font-size: 1.2em;
            font-weight: bold;
            display: grid;
            grid-template-columns: 100px 150px 1fr 120px;
            gap: 15px;
        }

        .list-item {
            display: grid;
            grid-template-columns: 100px 150px 1fr 120px;
            gap: 15px;
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            align-items: center;
            transition: background-color 0.3s ease;
        }

        .list-item:hover {
            background-color: #f8f9fa;
        }

        .student-class {
            font-weight: 600;
            color: #667eea;
        }

        .student-name {
            font-weight: 500;
            color: #333;
        }

        .student-id {
            color: #666;
            font-family: monospace;
        }

        .view-btn {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9em;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .view-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            max-width: 500px;
            width: 100%;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Student Card Styles (for modal) */
        .student-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
        }

        .card-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 25px;
            position: relative;
        }

        .card-header h2 {
            font-size: 1.8em;
            margin-bottom: 5px;
        }
        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 1.5em;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease;
        }

        .close-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .card-body {
            padding: 25px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-label {
            font-size: 0.85em;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 1.1em;
            color: #333;
            font-weight: 500;
        }

        .contact-section {
            grid-column: span 2;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            margin-top: 10px;
        }

        .badge {
            background: #e8f0fe;
            color: #667eea;
            padding: 5px 15px;
            border-radius: 25px;
            font-size: 0.9em;
            font-weight: 600;
            display: inline-block;
        }

        @media screen and (max-width: 768px) {
            .list-header {
                display: none;
            }
            
            .list-item {
                grid-template-columns: 1fr;
                gap: 10px;
                position: relative;
                padding: 20px;
            }
            
            .view-btn {
                width: 100%;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .contact-section {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Student Management System</h1>
        
        <!-- Student List -->
        <div class="student-list">
            <div class="list-header">
                <div>Class</div>
                <div>Name</div>
                <div>Student ID</div>
                <div>Action</div>
            </div>
            
            <div class="list-items" id="studentListItems">
                <?php
                // Sample student data array with more details
                $students = [
                    [
                        'id' => 1,
                        'name' => 'John Smith',
                        'class' => 'Grade 10-A',
                        'age' => 16,
                        'parent_name' => 'Robert Smith',
                        'parent_number' => '+1 (555) 123-4567',
                        'student_id' => 'STU-2024-001',
                        'email' => 'john.smith@school.edu',
                        'address' => '123 Oak Street',
                        'emergency_contact' => '+1 (555) 123-4568',
                        'blood_group' => 'O+',
                        'enrollment_date' => '2023-09-01'
                    ],
                    [
                        'id' => 2,
                        'name' => 'Emily Johnson',
                        'class' => 'Grade 11-B',
                        'age' => 17,
                        'parent_name' => 'Sarah Johnson',
                        'parent_number' => '+1 (555) 234-5678',
                        'student_id' => 'STU-2024-002',
                        'email' => 'emily.j@school.edu',
                        'address' => '456 Pine Avenue',
                        'emergency_contact' => '+1 (555) 234-5679',
                        'blood_group' => 'A+',
                        'enrollment_date' => '2022-09-01'
                    ],
                    [
                        'id' => 3,
                        'name' => 'Michael Brown',
                        'class' => 'Grade 9-C',
                        'age' => 15,
                        'parent_name' => 'David Brown',
                        'parent_number' => '+1 (555) 345-6789',
                        'student_id' => 'STU-2024-003',
                        'email' => 'michael.b@school.edu',
                        'address' => '789 Maple Road',
                        'emergency_contact' => '+1 (555) 345-6790',
                        'blood_group' => 'B-',
                        'enrollment_date' => '2023-09-01'
                    ],
                    [
                        'id' => 4,
                        'name' => 'Sophia Garcia',
                        'class' => 'Grade 12-A',
                        'age' => 18,
                        'parent_name' => 'Maria Garcia',
                        'parent_number' => '+1 (555) 456-7890',
                        'student_id' => 'STU-2024-004',
                        'email' => 'sophia.g@school.edu',
                        'address' => '321 Cedar Lane',
                        'emergency_contact' => '+1 (555) 456-7891',
                        'blood_group' => 'AB+',
                        'enrollment_date' => '2021-09-01'
                    ],
                    [
                        'id' => 5,
                        'name' => 'William Taylor',
                        'class' => 'Grade 10-B',
                        'age' => 16,
                        'parent_name' => 'James Taylor',
                        'parent_number' => '+1 (555) 567-8901',
                        'student_id' => 'STU-2024-005',
                        'email' => 'william.t@school.edu',
                        'address' => '654 Birch Street',
                        'emergency_contact' => '+1 (555) 567-8902',
                        'blood_group' => 'O-',
                        'enrollment_date' => '2023-09-01'
                    ],
                    [
                        'id' => 6,
                        'name' => 'Olivia Martinez',
                        'class' => 'Grade 11-A',
                        'age' => 17,
                        'parent_name' => 'Lisa Martinez',
                        'parent_number' => '+1 (555) 678-9012',
                        'student_id' => 'STU-2024-006',
                        'email' => 'olivia.m@school.edu',
                        'address' => '987 Elm Drive',
                        'emergency_contact' => '+1 (555) 678-9013',
                        'blood_group' => 'A-',
                        'enrollment_date' => '2022-09-01'
                    ],
                    [
                        'id' => 7,
                        'name' => 'James Anderson',
                        'class' => 'Grade 9-A',
                        'age' => 15,
                        'parent_name' => 'Robert Anderson',
                        'parent_number' => '+1 (555) 789-0123',
                        'student_id' => 'STU-2024-007',
                        'email' => 'james.a@school.edu',
                        'address' => '147 Spruce Street',
                        'emergency_contact' => '+1 (555) 789-0124',
                        'blood_group' => 'B+',
                        'enrollment_date' => '2023-09-01'
                    ],
                    [
                        'id' => 8,
                        'name' => 'Emma Wilson',
                        'class' => 'Grade 12-B',
                        'age' => 18,
                        'parent_name' => 'Patricia Wilson',
                        'parent_number' => '+1 (555) 890-1234',
                        'student_id' => 'STU-2024-008',
                        'email' => 'emma.w@school.edu',
                        'address' => '258 Willow Lane',
                        'emergency_contact' => '+1 (555) 890-1235',
                        'blood_group' => 'AB-',
                        'enrollment_date' => '2021-09-01'
                    ]
                ];
// Display each student in the list
                foreach ($students as $student) {
                    ?>
                    <div class="list-item">
                        <div class="student-class"><?php echo htmlspecialchars($student['class']); ?></div>
                        <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                        <div class="student-id"><?php echo htmlspecialchars($student['student_id']); ?></div>
                        <div>
                            <button class="view-btn" onclick="showStudentDetails(<?php echo $student['id']; ?>)">
                                View Details
                            </button>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
        
        <!-- Student Count -->
        <div style="text-align: center; color: white; opacity: 0.9;">
            <p>Total Students: <?php echo count($students); ?> | 
               <a href="#" style="color: white;" onclick="showAllStudents()">View All Cards</a>
            </p>
        </div>
    </div>

    <!-- Modal for Student Details -->
    <div class="modal" id="studentModal">
        <div class="modal-content" id="modalContent">
            <!-- Content will be loaded here dynamically -->
        </div>
    </div>

    <script>
        // Student data passed from PHP to JavaScript
        const students = <?php echo json_encode($students); ?>;
        const modal = document.getElementById('studentModal');
        const modalContent = document.getElementById('modalContent');

        function showStudentDetails(studentId) {
            const student = students.find(s => s.id === studentId);
            
            if (student) {
                modalContent.innerHTML = generateStudentCard(student);
                modal.classList.add('active');
                document.body.style.overflow = 'hidden'; // Prevent scrolling
            }
        }

        function generateStudentCard(student) {
            return `
                <div class="student-card">
                    <div class="card-header">
                        <h2>${escapeHtml(student.name)}</h2>
                        <div style="opacity: 0.9;">${escapeHtml(student.student_id)}</div>
                        <button class="close-btn" onclick="closeModal()">&times;</button>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Class</div>
                                <div class="info-value">${escapeHtml(student.class)}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Age</div>
                                <div class="info-value">${escapeHtml(student.age)} years</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Blood Group</div>
                                <div class="info-value">${escapeHtml(student.blood_group)}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Enrollment Date</div>
                                <div class="info-value">${escapeHtml(student.enrollment_date)}</div>
                            </div>
                            
                            <div class="contact-section">
                                <div class="info-item">
                                    <div class="info-label">Parent's Name</div>
                                    <div class="info-value">${escapeHtml(student.parent_name)}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Parent's Contact</div>
                                <div class="info-value">${escapeHtml(student.parent_number)}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Emergency Contact</div>
                                    <div class="info-value">${escapeHtml(student.emergency_contact)}</div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <div class="info-value">${escapeHtml(student.email)}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Address</div>
                                <div class="info-value">${escapeHtml(student.address)}</div>
                            </div>
                        </div>
                        
                        <span class="badge">Active Student</span>
                    </div>
                </div>
            `;
        }

        function showAllStudents() {
            // Create a grid of all student cards
            let allCardsHtml = '<div style="display: flex; flex-wrap: wrap; gap: 20px; justify-content: center;">';
            
            students.forEach(student => {
                allCardsHtml += `
                    <div style="flex: 0 1 300px;">
                        ${generateStudentCard(student)}
                    </div>
                `;
            });
            
            allCardsHtml += '</div>';
            
            modalContent.innerHTML = allCardsHtml;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto'; // Restore scrolling
        }

        // Close modal when clicking outside
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && modal.classList.contains('active')) {
                closeModal();
            }
        });

        // Helper function to escape HTML and prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>