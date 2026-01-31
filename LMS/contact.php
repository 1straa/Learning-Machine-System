<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - I-Acadsikatayo LMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Background with blur effect */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect fill="%23f5f5dc" width="100" height="100"/></svg>');
            background-size: cover;
            background-position: center;
            filter: blur(8px);
            opacity: 0.3;
            z-index: -2;
        }

        /* Gradient overlay */
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, 
                rgba(245, 245, 220, 0.95) 0%, 
                rgba(255, 255, 255, 0.92) 35%,
                rgba(218, 165, 32, 0.15) 65%,
                rgba(46, 125, 50, 0.25) 100%);
            z-index: -1;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            padding: 1.5rem 0;
            box-shadow: 0 4px 20px rgba(46, 125, 50, 0.3);
            position: relative;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
        }

        .logo i {
            font-size: 3rem;
            color: #daa520;
        }

        .logo h1 {
            font-size: 1.8rem;
            margin-bottom: 0.3rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .logo p {
            font-size: 1rem;
            opacity: 0.95;
            font-weight: 500;
        }

        .back-btn {
            background: rgba(218, 165, 32, 0.2);
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid #daa520;
        }

        .back-btn:hover {
            background: #daa520;
            transform: translateX(-5px);
        }

        .back-btn i {
            margin-right: 0.5rem;
        }

        /* Main Content */
        .main-content {
            padding: 3rem 0;
            min-height: calc(100vh - 300px);
        }

        .page-title {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-title h2 {
            font-size: 2.5rem;
            color: #1b5e20;
            margin-bottom: 0.5rem;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }

        .page-title p {
            font-size: 1.1rem;
            color: #666;
        }

        /* Contact Grid */
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        /* Contact Form */
        .contact-form-section {
            background: rgba(255, 255, 255, 0.9);
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(46, 125, 50, 0.15);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(218, 165, 32, 0.2);
        }

        .contact-form-section h3 {
            color: #1b5e20;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            border-bottom: 3px solid #daa520;
            padding-bottom: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            color: #2e7d32;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid rgba(46, 125, 50, 0.3);
            border-radius: 10px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2e7d32;
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 150px;
        }

        .submit-btn {
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            color: white;
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(46, 125, 50, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            width: 100%;
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(46, 125, 50, 0.4);
        }

        /* Contact Info */
        .contact-info-section {
            background: rgba(255, 255, 255, 0.9);
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(46, 125, 50, 0.15);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(218, 165, 32, 0.2);
        }

        .contact-info-section h3 {
            color: #1b5e20;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            border-bottom: 3px solid #daa520;
            padding-bottom: 0.5rem;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, rgba(46, 125, 50, 0.05) 0%, rgba(218, 165, 32, 0.05) 100%);
            border-radius: 15px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .info-item:hover {
            border-color: #daa520;
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(46, 125, 50, 0.1);
        }

        .info-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 5px 15px rgba(46, 125, 50, 0.3);
        }

        .info-icon i {
            color: #daa520;
            font-size: 1.5rem;
        }

        .info-content h4 {
            color: #2e7d32;
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .info-content p {
            color: #666;
            line-height: 1.6;
            margin: 0;
        }

        .info-content a {
            color: #2e7d32;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .info-content a:hover {
            color: #daa520;
        }

        /* Map Section */
        .map-section {
            background: rgba(255, 255, 255, 0.9);
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(46, 125, 50, 0.15);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(218, 165, 32, 0.2);
            grid-column: 1 / -1;
        }

        .map-section h3 {
            color: #1b5e20;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            border-bottom: 3px solid #daa520;
            padding-bottom: 0.5rem;
        }

        .map-container {
            width: 100%;
            height: 400px;
            border-radius: 15px;
            overflow: hidden;
            border: 3px solid rgba(46, 125, 50, 0.2);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .map-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* Footer */
        .footer {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            color: white;
            text-align: center;
            padding: 1.5rem 0;
            margin-top: 3rem;
            box-shadow: 0 -4px 20px rgba(46, 125, 50, 0.2);
        }

        .footer p {
            margin: 0;
            opacity: 0.95;
        }

        /* Success Message */
        .success-message {
            display: none;
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            color: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
            animation: slideDown 0.3s ease;
        }

        .success-message.show {
            display: block;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Responsive Design */
        @media (max-width: 968px) {
            .contact-grid {
                grid-template-columns: 1fr;
            }

            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .logo h1 {
                font-size: 1.3rem;
            }

            .logo i {
                font-size: 2rem;
            }

            .page-title h2 {
                font-size: 1.8rem;
            }

            .map-container {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-graduation-cap"></i>
                    <div>
                        <h1>I-Acadsikatayo: Learning Management System</h1>
                        <p>Metro Dagupan Colleges</p>
                    </div>
                </div>
                <a href="index.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Page Title -->
            <div class="page-title">
                <h2>Contact Us</h2>
                <p>We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
            </div>

            <!-- Contact Grid -->
            <div class="contact-grid">
                <!-- Contact Form -->
                <div class="contact-form-section">
                    <h3>Send Us a Message</h3>
                    <div id="successMessage" class="success-message">
                        <i class="fas fa-check-circle"></i> Your message has been sent successfully! We'll get back to you soon.
                    </div>
                    <form id="contactForm" onsubmit="handleSubmit(event)">
                        <div class="form-group">
                            <label for="name"><i class="fas fa-user"></i> Full Name *</label>
                            <input type="text" id="name" name="name" required placeholder="Enter your full name">
                        </div>
                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email Address *</label>
                            <input type="email" id="email" name="email" required placeholder="your.email@example.com">
                        </div>
                        <div class="form-group">
                            <label for="subject"><i class="fas fa-tag"></i> Subject *</label>
                            <input type="text" id="subject" name="subject" required placeholder="What is this about?">
                        </div>
                        <div class="form-group">
                            <label for="message"><i class="fas fa-comment"></i> Message *</label>
                            <textarea id="message" name="message" required placeholder="Type your message here..."></textarea>
                        </div>
                        <button type="submit" class="submit-btn">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </form>
                </div>

                <!-- Contact Information -->
                <div class="contact-info-section">
                    <h3>Get in Touch</h3>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="info-content">
                            <h4>Our Location</h4>
                            <p>National Highway, Barangay Salay<br>Mangaldan, Pangasinan<br>Philippines, 2432</p>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="info-content">
                            <h4>Phone Number</h4>
                            <p><a href="tel:+6375226367">(075) 522 6367</a></p>
                            <p style="font-size: 0.9rem; margin-top: 0.3rem;">Monday - Friday: 8:00 AM - 5:00 PM</p>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="info-content">
                            <h4>Email Address</h4>
                            <p><a href="mailto:inquiries@metrodagupancolleges.edu.ph">inquiries@metrodagupancolleges.edu.ph</a></p>
                            <p style="font-size: 0.9rem; margin-top: 0.3rem;">We'll respond within 24-48 hours</p>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-globe"></i>
                        </div>
                        <div class="info-content">
                            <h4>Website</h4>
                            <p><a href="http://www.metrodagupancolleges.edu.ph/" target="_blank">www.metrodagupancolleges.edu.ph</a></p>
                        </div>
                    </div>
                </div>

                <!-- Map Section -->
                <div class="map-section">
                    <h3>Find Us on the Map</h3>
                    <div class="map-container">
                        <!-- Google Maps Embed for Mangaldan, Pangasinan -->
                        <iframe 
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3841.8916582863285!2d120.40584931483476!3d16.063847388891!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33915d1fb8f0f2f7%3A0x7b8c7b4e4e4e4e4e!2sMangaldan%2C%20Pangasinan!5e0!3m2!1sen!2sph!4v1234567890123"
                            allowfullscreen="" 
                            loading="lazy" 
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 I-Acadsikatayo: Learning Management System - Metro Dagupan Colleges. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function handleSubmit(event) {
            event.preventDefault();
            
            // Get form data
            const form = document.getElementById('contactForm');
            const successMessage = document.getElementById('successMessage');
            
            // Here you would typically send the data to a server
            // For now, we'll just show the success message
            
            // Show success message
            successMessage.classList.add('show');
            
            // Reset form
            form.reset();
            
            // Scroll to success message
            successMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Hide success message after 5 seconds
            setTimeout(() => {
                successMessage.classList.remove('show');
            }, 5000);
            
            // In a real application, you would do something like this:
            /*
            const formData = new FormData(form);
            fetch('process_contact.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    successMessage.classList.add('show');
                    form.reset();
                }
            })
            .catch(error => console.error('Error:', error));
            */
        }
    </script>
</body>
</html>