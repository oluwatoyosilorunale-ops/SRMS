 <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ResultFlow · advanced SRMS UI</title>
    <!-- Font Awesome 6 (free) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Font: Inter & Space Grotesk for modern look -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&family=Space+Grotesk:wght@400;500&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f3f6fb;
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            padding: 32px 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .ui-wrapper {
            max-width: 1440px;
            width: 100%;
        }

        /* header / system ribbon */
        .system-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }
        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-icon {
            background: linear-gradient(145deg, #2563eb, #1e40af);
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            box-shadow: 0 10px 20px -5px rgba(37,99,235,0.3);
        }
        .logo-text h2 {
            font-weight: 700;
            font-size: 1.8rem;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #1e293b, #2563eb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .logo-text span {
            font-size: 0.9rem;
            font-weight: 500;
            color: #64748b;
            background: none;
            -webkit-text-fill-color: #64748b;
        }
        .year-badge {
            background: white;
            padding: 8px 18px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.9rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.02);
            border: 1px solid #e9eef3;
        }

        /* cards grid (all pages as panels) */
        .dashboard-grid {
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        .row-panels {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 28px;
        }

        /* shared card style */
        .card {
            background: rgba(255,255,255,0.75);
            backdrop-filter: blur(2px);
            background: #ffffff;
            border-radius: 32px;
            padding: 24px;
            box-shadow: 0 20px 35px -8px rgba(0,34,64,0.1), 0 0 0 1px rgba(0,0,0,0.02);
            transition: all 0.2s ease;
            border: 1px solid #ffffff50;
        }
        .card:hover {
            box-shadow: 0 24px 42px -12px rgba(37,99,235,0.15), 0 0 0 1px #cddffb;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .card-header h3 {
            font-weight: 600;
            font-size: 1.35rem;
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .badge {
            background: #e6edfc;
            color: #1d4ed8;
            padding: 5px 12px;
            border-radius: 40px;
            font-size: 0.8rem;
            font-weight: 600;
        }
 /* login section specific */
        .login-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
        }
        .login-card {
            background: white;
        }
        .login-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .field label {
            font-weight: 600;
            font-size: 0.9rem;
            color: #475569;
            letter-spacing: 0.2px;
        }
        .input-icon {
            display: flex;
            align-items: center;
            background: #f8fafc;
            border-radius: 20px;
            padding: 0 18px;
            border: 1.5px solid #e2e8f0;
            transition: 0.2s;
        }
        .input-icon:focus-within {
            border-color: #2563eb;
            background: white;
            box-shadow: 0 4px 10px rgba(37,99,235,0.1);
        }
        .input-icon i {
            color: #94a3b8;
        }
        .input-icon input {
            width: 100%;
            padding: 16px 12px;
            border: none;
            background: none;
            font-size: 1rem;
            outline: none;
        }
        .login-btn {
            background: #0f172a;
            color: white;
            border: none;
            padding: 16px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: default;
            box-shadow: 0 8px 18px #0f172a30;
        }
        .error-message {
            background: #fee2e2;
            border-radius: 30px;
            padding: 12px 18px;
            color: #b91c1c;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid #fecaca;
        }
/* student list */
        .search-bar {
            background: #f1f5f9;
            border-radius: 40px;
            padding: 8px 18px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        .search-bar i {
            color: #64748b;
        }
        .search-bar input {
            background: transparent;
            border: none;
            width: 100%;
            padding: 8px 0;
            outline: none;
        }
        .student-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fafcff;
            padding: 14px 18px;
            border-radius: 22px;
            margin-bottom: 8px;
            border: 1px solid #eef2f6;
        }
        .student-info {
            display: flex;
            gap: 15px;
            font-size: 0.95rem;
        }
        .student-actions i {
            margin: 0 6px;
            color: #64748b;
            background: white;
            padding: 8px;
            border-radius: 50%;
            box-shadow: 0 4px 8px #e2e8f0;
            transition: 0.2s;
        }
        .add-student-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-top: 20px;
        }
        .radio-group {
            display: flex;
            gap: 18px;
            align-items: center;
        }
        .radio-group label {
            display: flex;
            align-items: center;
            gap: 4px;
            font-weight: 500;
        }
        .btn-group {
            grid-column: span 2;
            display: flex;
            gap: 12px;
            margin-top: 10px;
        }
        .btn {
            padding: 14px 22px;
            border-radius: 40px;
            border: none;
            font-weight: 600;
            background: #eff4ff;
            color: #1e293b;
            flex: 1;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-primary {
            background: #2563eb;
            color: white;
            box-shadow: 0 6px 14px #2563eb60;
        }

        /* stats chips */
        .stats-mini {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin: 16px 0;
        }
        .stat-chip {
            background: #f1f5f9;
            padding: 8px 16px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .subject-list {
            margin: 16px 0;
        }
        .subject-item {
            background: #f8fafc;
            border-radius: 20px;
            padding: 14px 18px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
        }

        .result-compute {
            background: #f0f4fe;
            border-radius: 24px;
            padding: 20px;
            margin: 20px 0;
        }
        .result-table {
            width: 100%;
            border-collapse: collapse;
        }
        .result-table th {
            text-align: left;
            padding: 14px 6px;
            font-size: 0.8rem;
            color: #64748b;
        }
        .result-table td {
            padding: 10px 6px;
            border-top: 1px solid #e2e8f0;
        }

        .signature-line {
            display: flex;
            justify-content: space-between;
            margin-top: 24px;
            border-top: 2px dashed #cbd5e1;
            padding-top: 24px;
            font-weight: 500;
        }
        .print-button {
            background: white;
            border: 2px solid #2563eb;
            color: #2563eb;
            padding: 10px 22px;
            border-radius: 40px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="ui-wrapper">
        <!-- header with modern meta -->
        <div class="system-header">
            <div class="logo-area">
                <div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
                <div class="logo-text">
                    <h2>ResultFlow <span>• v2.0</span></h2>
                </div>
            </div>
            <div class="year-badge"><i class="far fa-calendar-alt" style="margin-right: 8px;"></i> 2024/2025 · First term</div>
        </div>

        <!-- login row : page 1 (left) + additional teacher preview (right) -->
        <div class="login-row">
            <!-- 1) LOGIN PAGE (advanced) -->
            <div class="card login-card">
                <div class="card-header">
                    <h3><i class="fas fa-lock" style="color:#2563eb;"></i> Secure login</h3>
                    <span class="badge">1 · Login</span>
                </div>
                <div class="login-form">
                    <div class="field">
                        <label><i class="far fa-envelope" style="margin-right:6px;"></i>Email</label>
                        <div class="input-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" placeholder="michael@srms.edu" value="admin@resultflow.com">
                        </div>
                    </div>
                    <div class="field">
                        <label><i class="fas fa-key"></i> Password</label>
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" value="········">
                        </div>
                    </div>
                    <button class="login-btn" disabled><i class="fas fa-arrow-right-to-bracket"></i> Login</button>
                    <div class="error-message">
                        <i class="fas fa-circle-exclamation"></i> Invalid credentials. Please try again.
                    </div>
                    <div style="display: flex; gap: 12px; font-size:0.9rem; color:#4b5563;">
                        <span><i class="fas-regular fa-circle-check"></i> 2FA optional</span>
                        <span><i class="far fa-question-circle"></i> Help</span>
                    </div>
                </div>
            </div>
<!-- quick stat + teacher welcome (page 4 condensed) -->
            <div class="card" style="background: linear-gradient(145deg, #ffffff, #f6faff);">
                <div class="card-header">
                    <h3><i class="fas fa-chalkboard-teacher" style="color:#2563eb;"></i> Teacher dashboard</h3>
                    <span class="badge">4 · preview</span>
                </div>
                <div style="display: flex; gap: 12px; align-items: center; margin-bottom: 20px;">
                    <div style="background: #2563eb; width: 48px; height: 48px; border-radius: 20px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.5rem;">A</div>
                    <div>
                        <h4 style="font-weight:700;">Welcome, Adebayo! 👋</h4>
                        <p style="color: #475569;">select term: <strong>First Term</strong> <i class="fas fa-chevron-down"></i></p>
                    </div>
                </div>
                <div class="subject-item">
                    <span><i class="fas fa-calculator"></i> Mathematics</span> <span>CA 30 · Exam 60 → <strong>90 (A)</strong></span>
                </div>
                <div style="display:flex; justify-content: space-between; font-weight:600; margin:10px 0">
                    <span>Total 520</span> <span>Average 86.7%</span> <span style="color:#2563eb;">Position 2nd</span>
                </div>
                <div><span class="print-button"><i class="fas fa-print"></i> Print result (PDF)</span></div>
            </div>
        </div>

        <!-- main grid: remaining pages 2,3,5 + extras -->
        <div class="dashboard-grid" style="margin-top:28px;">
            <!-- first row: Admin dashboard + Student management + Result computation compact -->
            <div class="row-panels">
                <!-- 2) ADMIN DASHBOARD -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-shield"></i> Admin dashboard</h3>
                        <span class="badge">2</span>
                    </div>
                    <div class="stats-mini">
                        <span class="stat-chip"><i class="fas fa-users"></i> 320 students</span>
                        <span class="stat-chip"><i class="fas fa-person-chalkboard"></i> 25 teachers</span>
                        <span class="stat-chip"><i class="fas fa-calendar"></i> Term 1</span>
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <span class="btn" style="flex:1;"><i class="fas fa-plus-circle"></i> Add subject</span>
                        <span class="btn" style="flex:1;"><i class="fas fa-filter"></i> Filter by class</span>
                    </div>
                    <div class="subject-list">
                        <p style="font-weight:600; margin-bottom:10px;"><i class="fas fa-book-open"></i> Subjects (JSS1–3)</p>
                        <div class="subject-item"><span>Mathematics</span> <span>Mr. Ade · 30 students</span></div>
                        <div class="subject-item"><span>English Studies</span> <span>Ms. Bola · 30 students</span></div>
                        <div class="subject-item"><span>Basic Science</span> <span>Mr. Kunle · 30 students</span></div>
                    </div>
                    <div style="color:#2563eb;"><i class="fas-regular fa-file-lines"></i> 12 active subjects</div>
                </div>
<!-- 3) STUDENT MANAGEMENT (with add student) -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-graduate"></i> Student management</h3>
                        <span class="badge">3</span>
                    </div>
                    <div class="search-bar">
                        <i class="fas fa-search"></i>
                        <input placeholder="Search students...">
                    </div>
                    <div class="student-row">
                        <div class="student-info">
                            <span style="font-weight:600;">John Doe</span>
                            <span style="color:#475569;">JSS1 · M · 001</span>
                        </div>
                        <div class="student-actions">
                            <i class="fas fa-edit"></i>
                            <i class="fas fa-trash-alt"></i>
                        </div>
                    </div>
                    <div class="student-row">
                        <div class="student-info">
                            <span style="font-weight:600;">Adebayo M.</span>
                            <span style="color:#475569;">JSS1 · M · 019</span>
                        </div>
                        <div class="student-actions">
                            <i class="fas fa-edit"></i>
                            <i class="fas fa-trash-alt"></i>
                        </div>
                    </div>
                    <h4 style="margin: 20px 0 12px;"><i class="fas fa-user-plus"></i> Add student</h4>
                    <div class="add-student-form">
                        <input class="input-icon" style="padding:12px; border-radius:20px; border:1px solid #e2e8f0;" placeholder="First name">
                        <input class="input-icon" style="padding:12; border-radius:20px; border:1px solid #e2e8f0;" placeholder="Last name">
                        <select class="input-icon" style="padding:12px; border-radius:20px;">
                            <option>JSS1</option><option>JSS2</option>
                        </select>
                        <div class="radio-group">
                            <label><i class="fas-regular fa-circle"></i> M</label>
                            <label><i class="fas-regular fa-circle"></i> F</label>
                        </div>
                        <input class="input-icon" style="padding:12px;" type="text" placeholder="DOB: 30/10/2012">
                        <div class="btn-group">
                            <span class="btn"><i class="fas fa-save"></i> Save</span>
                            <span class="btn">Cancel</span>
                        </div>
                    </div>
                </div>
<!-- 5 partial + result computation (full card) -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-calculator"></i> Result computation</h3>
                        <span class="badge">5</span>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;">
                        <span class="badge" style="background:#2563eb; color:white;">JSS1</span>
                        <span class="badge">First Term</span>
                        <span class="btn" style="padding:6px 16px;"><i class="fas-regular fa-gear"></i> Compute</span>
                    </div>
                    <!-- result table preview -->
                    <table class="result-table">
                        <tr><th>Subject</th><th>CA</th><th>Exam</th><th>Total</th><th>Grade</th></tr>
                        <tr><td>Mathematics</td><td>30</td><td>60</td><td>90</td><td>A</td></tr>
                        <tr><td>English</td><td>28</td><td>62</td><td>90</td><td>A</td></tr>
                        <tr><td>Basic Sci.</td><td>25</td><td>55</td><td>80</td><td>B</td></tr>
                    </table>
                    <div style="display:flex; justify-content:space-between; margin-top:20px; font-weight:700;">
                        <span>Total: 520</span> <span>Avg: 86.7%</span> <span>Pos: 2nd</span>
                    </div>
                </div>
            </div>

            <!-- second row: detailed result sheet (page 5 extended) + extra interfaces -->
            <div class="row-panels">
                <!-- FULL RESULT SHEET (like page 5 but enhanced) -->
                <div class="card" style="grid-column: span 2; background: #fcfdff;">
                    <div class="card-header">
                        <h3><i class="fas fa-file-certificate"></i> Term result slip · First term 2025</h3>
                        <span class="badge">detailed</span>
                    </div>
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 28px;">
                        <!-- left: school identity -->
                        <div>
                            <div style="border-bottom: 2px solid #2563eb; padding-bottom: 16px; margin-bottom: 16px;">
                                <h2 style="font-weight:800; letter-spacing:-0.5px;">SCHOOL OF EXCELLENCE</h2>
                                <p style="color:#334155;">12, Education Avenue, Lagos · info@soe.edu.ng</p>
                            </div>
                            <div style="display: flex; gap: 32px; flex-wrap: wrap;">
                                <p><strong>Student:</strong> Adebayo Michael</p>
                                <p><strong>Class:</strong> JSS1</p>
                                <p><strong>Reg No:</strong> 2024/019</p>
                            </div>
                        </div>
                        <div style="background:#eef4ff; border-radius: 28px; padding: 16px;">
                            <p><i class="fas fa-medal" style="color:#2563eb;"></i> position: 2nd (out of 32)</p>
                            <p><i class="fas fa-chart-line"></i> average: 86.7%</p>
                        </div>
                    </div>
                    <table class="result-table" style="margin-top: 16px;">
                        <thead><tr style="background:#f1f5f9;"><th>Subject</th><th>CA (40)</th><th>Exam (60)</th><th>Total</th><th>Grade</th><th>Remark</th></tr></thead>
                        <tbody>
                            <tr><td>Mathematics</td><td>30</td><td>60</td><td>90</td><td>A</td><td>Excellent</td></tr>
                            <tr><td>English Studies</td><td>28</td><td>62</td><td>90</td><td>A</td><td>Excellent</td></tr>
                            <tr><td>Basic Science</td><td>25</td><td>55</td><td>80</td><td>B</td><td>Very good</td></tr>
                            <tr><td>Social Studies</td><td>26</td><td>58</td><td>84</td><td>B+</td><td>Good</td></tr>
                            <tr><td>Agricultural Sci.</td><td>24</td><td>52</td><td>76</td><td>B</td><td>Good</td></tr>
                        </tbody>
                    </table>
                    <div style="display: flex; justify-content: space-between; margin-top: 28px;">
                        <div style="font-style: italic;">Pupil signature ______________________</div>
                        <div><span class="print-button"><i class="fas fa-print"></i> Print result</span></div>
                    </div>
                    <div style="margin-top:18px; display:flex; gap: 30px;">
                        <span><i class="fas fa-calendar-check"></i> 20/03/2025</span>
                        <span><i class="fas fa-check-circle" style="color:#22c55e;"></i> Approved by principal</span>
                    </div>
                </div>

                <!-- quick extra: subject overview / add subject -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-layer-group"></i> subject bank</h3>
                        <span class="badge">JSS1-3</span>
                    </div>
                    <div class="btn-group" style="grid-column:span1; flex-direction:column;">
                        <span class="btn btn-primary"><i class="fas fa-plus"></i> Add new subject</span>
                        <span class="btn"><i class="fas fa-upload"></i> Bulk upload</span>
                    </div>
                    <div style="margin-top:20px;">
                        <div class="subject-item"><span>📐 Mathematics</span> <span>Mr. Adebayo</span></div>
                        <div class="subject-item"><span>📖 English</span> <span>Ms. Chioma</span></div>
                        <div class="subject-item"><span>🔬 Basic Science</span> <span>Mr. Kunle</span></div>
                        <div class="subject-item"><span>🌍 Social Studies</span> <span>Mrs. Funke</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- micro footer / additional term summary -->
        <div style="display: flex; justify-content: space-between; margin-top: 32px; color:#4b5563; background: white; border-radius: 60px; padding: 16px 28px; border:1px solid #e9eef3;">
            <span><i class="fas-regular fa-clock"></i> Active term: First term · 2025</span>
            <span><i class="fas fa-chart-simple"></i> Total students 338 | Teachers 27 | Subjects 14</span>
            <span><i class="fas fa-cloud"></i> ResultFlow · advanced UI concept</span>
        </div>

    </div> <!-- ui-wrapper -->
</body>
</html>