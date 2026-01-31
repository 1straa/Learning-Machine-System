<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>I-Acadsikatayo: Learning Management System</title>
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <img src="LMS logo.jpg" alt="I-Acadsikatayo logo" />
                    <div>
                        <h1>I-Acadsikatayo: Learning Management System</h1>
                        <p>Metro Dagupan Colleges</p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Hero Section -->
            <div class="hero">
                <h2>Welcome to Learning Management System</h2>
                <p>Empower education with our comprehensive platform designed for administrators, faculty, and students to collaborate seamlessly.</p>

                <!-- Features -->
                <div class="features">
                    <div class="feature-card">
                        <i class="fas fa-book"></i>
                        <h3>Subject Management</h3>
                        <p>Create, organize, and deliver engaging subject with ease</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-chart-line"></i>
                        <h3>Progress Tracking</h3>
                        <p>Monitor student performance and learning analytics</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-award"></i>
                        <h3>Assessments</h3>
                        <p>Create and grade assignments with advanced tools</p>
                    </div>
                </div>
            </div>

            <!-- Login Options -->
            <div class="login-options">
                <!-- Admin Login -->
                <div class="login-card">
                    <div class="login-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Administrator</h3>
                    <p>Manage system settings, users, and oversee platform operations with advanced administrative controls.</p>
                    <a href="admin_login.php" class="btn btn-primary">Administrator Login</a>
                </div>

                <!-- Faculty & Student Login -->
                <div class="login-card">
                    <div class="login-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Faculty & Students</h3>
                    <p>Access courses, assignments, and educational resources. Choose your role during the login process.</p>
                    <a href="user_login.php" class="btn btn-secondary">Faculty & Student Login</a>
                </div>
            </div>
        </div>
    </main>

    <!-- Enhanced Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <!-- About Section -->
                <div class="footer-section">
                    <h3>About Us</h3>
                    <p>Metro Dagupan Colleges is committed to providing quality education through innovative learning solutions and comprehensive academic programs.</p>
                    <div class="social-icons">
                        <a href="mailto:inquiries@metrodagupancolleges.edu.ph" class="social-icon" title="Email Us">
                            <i class="fas fa-envelope"></i>
                        </a>
                        <a href="#" class="social-icon" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="#" onclick="openModal('termsModal'); return false;"><i class="fas fa-file-contract"></i> Terms & Conditions</a></li>
                        <li><a href="#" onclick="openModal('aboutModal'); return false;"><i class="fas fa-info-circle"></i> About Us</a></li>
                        <li><a href="contact.php"><i class="fas fa-envelope"></i> Contact Us</a></li>
                        <li><a href="http://www.metrodagupancolleges.edu.ph/" target="_blank"><i class="fas fa-globe"></i> Official Website</a></li>
                    </ul>
                </div>

                <!-- Contact Info -->
                <div class="footer-section">
                    <h3>Contact Information</h3>
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>National Highway, Barangay Salay, Mangaldan, Philippines, 2432</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <span>(075) 522 6367</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <span>inquiries@metrodagupancolleges.edu.ph</span>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; 2025 I-Acadsikatayo: Learning Management System - Metro Dagupan Colleges. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Terms & Conditions Modal -->
    <div id="termsModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('termsModal')">&times;</span>
            <h2>Terms & Conditions</h2>
            
            <p><strong>Last Updated:</strong> January 2025</p>
            
            <p>Welcome to I-Acadsikatayo Learning Management System of Metro Dagupan Colleges. By accessing and using this platform, you agree to comply with and be bound by the following terms and conditions.</p>

            <h3>1. Account Registration & Access</h3>
            <ul>
                <li>Users must provide accurate and complete information during registration</li>
                <li>Each user is responsible for maintaining the confidentiality of their account credentials</li>
                <li>Accounts are non-transferable and for individual use only</li>
                <li>Unauthorized access or sharing of accounts is strictly prohibited</li>
                <li>The institution reserves the right to suspend or terminate accounts that violate these terms</li>
            </ul>

            <h3>2. User Responsibilities</h3>
            <ul>
                <li>Users must use the platform for educational purposes only</li>
                <li>Respect intellectual property rights of all content and materials</li>
                <li>Maintain professional and respectful communication with all users</li>
                <li>Report any technical issues or suspicious activities immediately</li>
                <li>Students must complete assignments and assessments within designated timeframes</li>
            </ul>

            <h3>3. Academic Integrity</h3>
            <ul>
                <li>All submitted work must be original and properly cited</li>
                <li>Plagiarism, cheating, or any form of academic dishonesty is strictly prohibited</li>
                <li>Collaborative work must be authorized by the instructor</li>
                <li>Violations may result in academic penalties as per institutional policies</li>
            </ul>

            <h3>4. Privacy & Data Protection</h3>
            <ul>
                <li>Metro Dagupan Colleges respects user privacy and protects personal information</li>
                <li>User data is collected and used solely for educational and administrative purposes</li>
                <li>Information will not be shared with third parties without consent, except as required by law</li>
                <li>Users have the right to access and request correction of their personal data</li>
            </ul>

            <h3>5. Content Guidelines</h3>
            <ul>
                <li>Users must not upload or share offensive, inappropriate, or illegal content</li>
                <li>All materials must comply with copyright laws and institutional policies</li>
                <li>The institution reserves the right to remove content that violates these guidelines</li>
                <li>Course materials are for enrolled students only and must not be redistributed</li>
            </ul>

            <h3>6. System Usage</h3>
            <ul>
                <li>Users must not attempt to breach system security or access restricted areas</li>
                <li>Automated data collection tools or bots are prohibited</li>
                <li>Users must not interfere with the platform's operation or other users' access</li>
                <li>Report any security vulnerabilities to the IT department immediately</li>
            </ul>

            <h3>7. Intellectual Property</h3>
            <ul>
                <li>All platform content, including design and software, is owned by Metro Dagupan Colleges</li>
                <li>Course materials are protected by copyright and for educational use only</li>
                <li>Users retain ownership of their original submitted work</li>
                <li>The institution may use anonymized student work for educational improvement purposes</li>
            </ul>

            <h3>8. Limitation of Liability</h3>
            <ul>
                <li>Metro Dagupan Colleges strives to maintain platform availability but does not guarantee uninterrupted access</li>
                <li>The institution is not liable for any data loss due to technical issues</li>
                <li>Users are responsible for maintaining backups of their important work</li>
                <li>The platform is provided "as is" without warranties of any kind</li>
            </ul>

            <h3>9. Modifications to Terms</h3>
            <p>Metro Dagupan Colleges reserves the right to modify these terms at any time. Users will be notified of significant changes, and continued use of the platform constitutes acceptance of updated terms.</p>

            <h3>10. Contact Information</h3>
            <p>For questions regarding these terms and conditions, please contact:</p>
            <p><strong>Email:</strong> inquiries@metrodagupancolleges.edu.ph<br>
            <strong>Phone:</strong> (075) 522 6367</p>

            <p style="margin-top: 2rem;"><strong>By using I-Acadsikatayo LMS, you acknowledge that you have read, understood, and agree to be bound by these Terms & Conditions.</strong></p>
        </div>
    </div>

    <!-- About Us Modal -->
    <div id="aboutModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('aboutModal')">&times;</span>
            <h2>About Metro Dagupan Colleges</h2>
            
            <h3>Our Institution</h3>
            <p>Metro Dagupan Colleges is a premier educational institution located in Mangaldan, Pangasinan, dedicated to providing quality education and fostering academic excellence. We are committed to developing well-rounded individuals who are prepared to meet the challenges of the modern world.</p>

            <h3>Our Mission</h3>
            <p>To provide accessible, affordable, and quality education that empowers students with knowledge, skills, and values necessary for personal growth, professional success, and meaningful contribution to society.</p>

            <h3>Our Vision</h3>
            <p>To be a leading educational institution recognized for academic excellence, innovation in teaching and learning, and producing graduates who are globally competitive and socially responsible.</p>

            <h3>Core Values</h3>
            <ul>
                <li><strong>Excellence:</strong> We pursue the highest standards in education and service</li>
                <li><strong>Integrity:</strong> We uphold honesty, transparency, and ethical conduct</li>
                <li><strong>Innovation:</strong> We embrace change and continuously improve our methods</li>
                <li><strong>Inclusivity:</strong> We provide equal opportunities for all learners</li>
                <li><strong>Community:</strong> We foster collaboration and social responsibility</li>
            </ul>

            <h3>I-Acadsikatayo Learning Management System</h3>
            <p>I-Acadsikatayo (which means "Let's Study Together" in the local language) is our comprehensive Learning Management System designed to enhance the educational experience through technology. The platform enables seamless interaction between administrators, faculty, and students, providing tools for course management, assignments, assessments, and collaborative learning.</p>

            <h3>Key Features</h3>
            <ul>
                <li><strong>Course Management:</strong> Comprehensive tools for creating and organizing courses</li>
                <li><strong>Interactive Learning:</strong> Engaging multimedia content and resources</li>
                <li><strong>Assessment Tools:</strong> Flexible options for assignments, quizzes, and examinations</li>
                <li><strong>Progress Tracking:</strong> Real-time monitoring of student performance and analytics</li>
                <li><strong>Communication:</strong> Integrated messaging and announcement systems</li>
                <li><strong>Mobile Access:</strong> Learn anytime, anywhere on any device</li>
            </ul>

            <h3>Our Commitment</h3>
            <p>Metro Dagupan Colleges is committed to leveraging technology to provide an enhanced learning experience. Through I-Acadsikatayo, we aim to bridge the gap between traditional and digital education, ensuring that our students receive the best possible preparation for their future careers.</p>

            <h3>Get in Touch</h3>
            <p>We welcome inquiries, feedback, and partnerships that align with our mission of educational excellence.</p>
            <p><strong>Address:</strong> National Highway, Barangay Salay, Mangaldan, Philippines, 2432<br>
            <strong>Phone:</strong> (075) 522 6367<br>
            <strong>Email:</strong> inquiries@metrodagupancolleges.edu.ph<br>
            <strong>Website:</strong> www.metrodagupancolleges.edu.ph</p>
        </div>
    </div>

    <script>
        // Modal Functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modals = document.querySelectorAll('.modal.active');
                modals.forEach(modal => {
                    modal.classList.remove('active');
                    document.body.style.overflow = 'auto';
                });
            }
        });
    </script>
</body>
</html>