<?php
/**
 * Database Migrations
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/app/Utils/Database.php';

echo "Running migrations...\n";

try {
    $db = Database::connect();
    echo "Connected to database.\n";
    
    // Create migrations table
    $db->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            executed_at TIMESTAMP DEFAULT NOW()
        )
    ");
    
    // Get executed migrations
    $executed = Database::fetchAll("SELECT name FROM migrations");
    $executedNames = array_column($executed, 'name');
    
    // Migration files
    $migrations = [
        '001_create_roles_table' => "
            CREATE TABLE IF NOT EXISTS roles (
                id SERIAL PRIMARY KEY,
                name VARCHAR(50) UNIQUE NOT NULL,
                display_name VARCHAR(100),
                level INT DEFAULT 0,
                permissions JSONB DEFAULT '{}',
                created_at TIMESTAMP DEFAULT NOW()
            );
            
            INSERT INTO roles (name, display_name, level) VALUES
                ('admin', 'Administrator', 100),
                ('manager', 'Manager', 80),
                ('staff', 'Staff', 50),
                ('photographer', 'Photographer', 30),
                ('model', 'Model', 25),
                ('client', 'Client', 20),
                ('registered', 'Registered User', 10),
                ('guest', 'Guest', 0)
            ON CONFLICT (name) DO NOTHING;
        ",
        
        '002_create_users_table' => "
            CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                username VARCHAR(50) UNIQUE,
                email VARCHAR(255) UNIQUE NOT NULL,
                role VARCHAR(50) DEFAULT 'registered',
                avatar_url TEXT,
                google_id VARCHAR(255),
                stripe_customer_id VARCHAR(255),
                email_verified BOOLEAN DEFAULT FALSE,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT NOW(),
                updated_at TIMESTAMP DEFAULT NOW(),
                last_login TIMESTAMP
            );
            
            CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
            CREATE INDEX IF NOT EXISTS idx_users_google_id ON users(google_id);
        ",
        
        '003_create_sessions_table' => "
            CREATE TABLE IF NOT EXISTS sessions (
                id SERIAL PRIMARY KEY,
                user_id INT REFERENCES users(id) ON DELETE CASCADE,
                token_hash TEXT NOT NULL,
                ip_address VARCHAR(50),
                user_agent TEXT,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT NOW()
            );
            
            CREATE INDEX IF NOT EXISTS idx_sessions_token ON sessions(token_hash);
            CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id);
        ",
        
        '004_create_otp_codes_table' => "
            CREATE TABLE IF NOT EXISTS otp_codes (
                id SERIAL PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                code_hash TEXT NOT NULL,
                attempts INT DEFAULT 0,
                ip_address VARCHAR(50),
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT NOW()
            );
            
            CREATE INDEX IF NOT EXISTS idx_otp_email ON otp_codes(email);
        ",
        
        '005_create_galleries_table' => "
            CREATE TABLE IF NOT EXISTS galleries (
                id SERIAL PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                type VARCHAR(20) DEFAULT 'public',
                category VARCHAR(50),
                cover_image_id INT,
                created_by INT REFERENCES users(id),
                shoot_date DATE,
                is_pinned BOOLEAN DEFAULT FALSE,
                pin_order INT,
                view_count INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT NOW(),
                updated_at TIMESTAMP DEFAULT NOW()
            );
            
            CREATE INDEX IF NOT EXISTS idx_galleries_type ON galleries(type);
            CREATE INDEX IF NOT EXISTS idx_galleries_category ON galleries(category);
        ",
        
        '006_create_images_table' => "
            CREATE TABLE IF NOT EXISTS images (
                id SERIAL PRIMARY KEY,
                gallery_id INT REFERENCES galleries(id) ON DELETE CASCADE,
                uploader_id INT REFERENCES users(id),
                filename VARCHAR(255) NOT NULL,
                original_filename VARCHAR(255),
                caption TEXT,
                metadata JSONB DEFAULT '{}',
                is_pinned BOOLEAN DEFAULT FALSE,
                pin_order INT,
                is_watermarked BOOLEAN DEFAULT FALSE,
                view_count INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT NOW()
            );
            
            CREATE INDEX IF NOT EXISTS idx_images_gallery ON images(gallery_id);
        ",
        
        '007_create_image_versions_table' => "
            CREATE TABLE IF NOT EXISTS image_versions (
                id SERIAL PRIMARY KEY,
                image_id INT REFERENCES images(id) ON DELETE CASCADE,
                type VARCHAR(20) NOT NULL,
                r2_path TEXT NOT NULL,
                r2_url TEXT,
                width INT,
                height INT,
                file_size BIGINT,
                created_at TIMESTAMP DEFAULT NOW()
            );
            
            CREATE INDEX IF NOT EXISTS idx_image_versions_image ON image_versions(image_id);
        ",
        
        '008_create_bookings_table' => "
            CREATE TABLE IF NOT EXISTS bookings (
                id SERIAL PRIMARY KEY,
                unique_link VARCHAR(255) UNIQUE NOT NULL,
                user_id INT REFERENCES users(id),
                service_type VARCHAR(100),
                date_start TIMESTAMP,
                date_end TIMESTAMP,
                duration_hours DECIMAL(4,2),
                status VARCHAR(20) DEFAULT 'requested',
                questionnaire_answers JSONB DEFAULT '{}',
                add_ons JSONB DEFAULT '{}',
                total_price DECIMAL(10,2),
                deposit_amount DECIMAL(10,2),
                notes TEXT,
                created_at TIMESTAMP DEFAULT NOW(),
                updated_at TIMESTAMP DEFAULT NOW()
            );
            
            CREATE INDEX IF NOT EXISTS idx_bookings_user ON bookings(user_id);
            CREATE INDEX IF NOT EXISTS idx_bookings_status ON bookings(status);
            CREATE INDEX IF NOT EXISTS idx_bookings_date ON bookings(date_start);
        ",
        
        '009_create_payments_table' => "
            CREATE TABLE IF NOT EXISTS payments (
                id SERIAL PRIMARY KEY,
                booking_id INT REFERENCES bookings(id),
                user_id INT REFERENCES users(id),
                method VARCHAR(50),
                provider VARCHAR(20) DEFAULT 'stripe',
                provider_payment_id TEXT,
                amount DECIMAL(10,2) NOT NULL,
                fee_amount DECIMAL(10,2) DEFAULT 0,
                currency VARCHAR(3) DEFAULT 'USD',
                status VARCHAR(20) DEFAULT 'pending',
                is_deposit BOOLEAN DEFAULT TRUE,
                metadata JSONB DEFAULT '{}',
                created_at TIMESTAMP DEFAULT NOW()
            );
            
            CREATE INDEX IF NOT EXISTS idx_payments_booking ON payments(booking_id);
            CREATE INDEX IF NOT EXISTS idx_payments_status ON payments(status);
        ",
        
        '010_create_contracts_table' => "
            CREATE TABLE IF NOT EXISTS contract_templates (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(50),
                content TEXT NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT NOW(),
                updated_at TIMESTAMP DEFAULT NOW()
            );
            
            CREATE TABLE IF NOT EXISTS contracts (
                id SERIAL PRIMARY KEY,
                template_id INT REFERENCES contract_templates(id),
                booking_id INT REFERENCES bookings(id),
                user_id INT REFERENCES users(id),
                title VARCHAR(255),
                content TEXT NOT NULL,
                signature_data JSONB,
                status VARCHAR(20) DEFAULT 'pending',
                sent_at TIMESTAMP,
                signed_at TIMESTAMP,
                pdf_path TEXT,
                created_at TIMESTAMP DEFAULT NOW()
            );
            
            CREATE INDEX IF NOT EXISTS idx_contracts_booking ON contracts(booking_id);
            CREATE INDEX IF NOT EXISTS idx_contracts_status ON contracts(status);
        ",
        
        '011_create_messages_table' => "
            CREATE TABLE IF NOT EXISTS message_threads (
                id SERIAL PRIMARY KEY,
                user_id INT REFERENCES users(id),
                subject VARCHAR(255),
                status VARCHAR(20) DEFAULT 'open',
                last_message_at TIMESTAMP,
                unread_count INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT NOW()
            );
            
            CREATE TABLE IF NOT EXISTS messages (
                id SERIAL PRIMARY KEY,
                thread_id INT REFERENCES message_threads(id) ON DELETE CASCADE,
                sender_id INT REFERENCES users(id),
                content TEXT NOT NULL,
                source VARCHAR(20) DEFAULT 'website',
                attachments JSONB DEFAULT '[]',
                read_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT NOW()
            );
            
            CREATE INDEX IF NOT EXISTS idx_messages_thread ON messages(thread_id);
        ",
        
        '012_create_analytics_table' => "
            CREATE TABLE IF NOT EXISTS analytics_events (
                id SERIAL PRIMARY KEY,
                event_type VARCHAR(100) NOT NULL,
                user_id INT REFERENCES users(id),
                session_id VARCHAR(255),
                resource_type VARCHAR(50),
                resource_id INT,
                ip_address VARCHAR(50),
                user_agent TEXT,
                device_type VARCHAR(20),
                referrer TEXT,
                metadata JSONB DEFAULT '{}',
                created_at TIMESTAMP DEFAULT NOW()
            );
            
            CREATE INDEX IF NOT EXISTS idx_analytics_type ON analytics_events(event_type);
            CREATE INDEX IF NOT EXISTS idx_analytics_date ON analytics_events(created_at);
        ",
        
        '013_create_settings_table' => "
            CREATE TABLE IF NOT EXISTS settings (
                id SERIAL PRIMARY KEY,
                key VARCHAR(100) UNIQUE NOT NULL,
                value JSONB NOT NULL,
                updated_at TIMESTAMP DEFAULT NOW()
            );
            
            INSERT INTO settings (key, value) VALUES
                ('pricing', '{\"personal\": {\"2hr\": 200, \"4hr\": 375, \"6hr\": 525}, \"product\": {\"2hr\": 250, \"4hr\": 450, \"6hr\": 600}}'),
                ('deposit_percent', '50'),
                ('buffer_hours', '4'),
                ('payment_methods', '{\"card\": true, \"apple_pay\": true, \"google_pay\": true}')
            ON CONFLICT (key) DO NOTHING;
        ",

        '014_create_gallery_links_table' => "
            CREATE TABLE IF NOT EXISTS gallery_links (
                id SERIAL PRIMARY KEY,
                gallery_id INT REFERENCES galleries(id) ON DELETE CASCADE,
                token VARCHAR(255) UNIQUE NOT NULL,
                permissions JSONB DEFAULT '{\"view\": true, \"download\": false}',
                expires_at TIMESTAMP,
                password_hash TEXT,
                access_count INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT NOW()
            );
            
            CREATE INDEX IF NOT EXISTS idx_gallery_links_token ON gallery_links(token);
        "
    ];
    
    foreach ($migrations as $name => $sql) {
        if (in_array($name, $executedNames)) {
            echo "Skipping: $name (already executed)\n";
            continue;
        }
        
        echo "Running: $name\n";
        $db->exec($sql);
        Database::query("INSERT INTO migrations (name) VALUES (?)", [$name]);
        echo "Completed: $name\n";
    }
    
    echo "\nAll migrations completed successfully!\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
