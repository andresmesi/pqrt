# Pqrt - Open Source Image Threading Platform

Pqrt is an open-source platform where users can create threads by uploading images and adding comments with tags. The platform supports nested replies, user authentication via Google OAuth, and caching for improved performance.

## Features

- **Image Upload:** Users can upload images with their posts.
- **Threaded Discussions:** Create new threads or reply to existing ones.
- **Google OAuth Authentication:** Secure login via Google.
- **Caching:** Static HTML caching for fast page loads.
- **Responsive Design:** Supports light and dark modes.
- **Public Profiles:** Users can view their posts via a public URL.
- **Detailed Documentation:** Extensive inline code comments for easier understanding and contributions.

## Setup Instructions

git clone https://github.com/andresmesi/pqrt.git
cd pqrt
composer install
mkdir uploads cache
chmod 775 uploads cache
mysql -u your_db_user -p your_db_name < db.sql

Configuration via Setup Script (Optional): You can run the provided setup.php script in your browser to configure your settings interactively. For security, remove or restrict access to setup.php after configuration.
FontAwesome: Ensure the FontAwesome assets are available in the /fontawesome/ directory. If not, install FontAwesome manually or adjust the paths.
Deploy the Application: Deploy the project on your PHP-enabled web server.

Licence MIT
