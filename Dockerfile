FROM dunglas/frankenphp:php8.3

# Install ekstensi PHP yang dibutuhkan (mysqli untuk koneksi TiDB)
RUN install-php-extensions mysqli pdo_mysql

# Copy semua file project ke dalam container
COPY . /app

# Set direktori kerja
WORKDIR /app

# Expose port (Vercel akan otomatis mendeteksi ini)
EXPOSE 80

# PERINTAH SAKTI: Jalankan FrankenPHP dengan Caddyfile kita
CMD ["frankenphp", "run", "--config", "/app/Caddyfile", "--adapter", "caddyfile"]