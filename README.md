# Memorial Website - Dynamic Installation

A dynamic memorial website system written in PHP that can be deployed for any loved one.

## Features

- **Easy Installation**: First-time setup wizard guides you through configuration
- **Dynamic Configuration**: Each installation is customized for a specific memorial
- **Entry Moderation**: Approve or decline memory submissions before they appear publicly
- **Photo Upload**: Optional memorial photo display
- **No External Database**: Uses SQLite for zero-configuration deployment
- **Timezone Support**: Configurable timezone for accurate timestamps
- **Security**: IP-based login throttling and password hashing

## Requirements

- **PHP 8.1+** with **pdo_sqlite** extension
- Web server (Apache, Nginx, etc.)
- Write permissions for the installation directory

## Installation

1. **Upload files** to your web server
2. **Visit your website** in a browser
3. **Complete the installation wizard**:
   - Enter the name of your loved one
   - Provide your administrator email
   - Set a secure password
   - Optionally upload a memorial photo
   - Select your timezone
4. **Access the admin panel** at `~/admin/`

### First Login
After installation, log in with:
- **Email**: The email you provided during installation
- **Password**: The password you set during installation

### Post-Installation
- Change admin credentials in `~/admin/settings.php`
- Moderate new entries at `~/admin/`
- View the public memorial page at the root URL

## Workflow

1. **Visitors submit memories** via the public form
2. **Admin reviews submissions** in the admin panel
3. **Approved entries** appear on the public memorial page
4. **Declined entries** are moved to the bin
5. **Deleted entries** can be restored from the bin

## Security Notes

- Admin login attempts are rate-limited by IP address
- Passwords are hashed using bcrypt
- Configuration file is auto-generated and should not be committed to version control

## File Structure

```
install.php              # One-time installation wizard
config.php              # Auto-generated configuration (created during install)
memorial.db             # SQLite database (created during install)
index.php               # Public memorial page
form.php                # Memory submission form
admin/                  # Administration panel
  index.php             # Entry moderation
  settings.php          # Admin settings
images/memorial/        # Uploaded memorial photos
```

## Customization

After installation, you can customize:
- Site appearance via `styles/style.css` and `styles/theme.css`
- Memorial photo (upload via admin settings or replace in `images/memorial/`)
- Text formatting options in `service/storage.php`

## Reinstallation

To reset and reinstall:
1. Delete `config.php`
2. Delete `memorial.db`
3. Clear `images/memorial/` directory
4. Visit the site to run installation wizard again

## License

Licensed under BSD 3-Clause License

## Support

For issues or questions, please refer to the documentation in the `docs/` folder.