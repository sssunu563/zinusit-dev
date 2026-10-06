#!/bin/bash
# Generate Self-Signed SSL Certificate untuk IP Address

echo "🔐 Generating Self-Signed SSL Certificate for IP: 10.62.8.101"
echo "================================================================"

# Buat directory jika belum ada
sudo mkdir -p /etc/ssl/private
sudo mkdir -p /etc/ssl/certs

# Generate private key
sudo openssl genrsa -out /etc/ssl/private/zinusit.key 2048

# Generate certificate (valid 365 hari)
sudo openssl req -new -x509 -key /etc/ssl/private/zinusit.key \
    -out /etc/ssl/certs/zinusit.crt -days 365 \
    -subj "/C=ID/ST=West Java/L=Bogor/O=Zinus/OU=IT/CN=10.62.8.101" \
    -addext "subjectAltName=IP:10.62.8.101,DNS:it.zinus.co.id,DNS:localhost"

# Set proper permissions
sudo chmod 600 /etc/ssl/private/zinusit.key
sudo chmod 644 /etc/ssl/certs/zinusit.crt

echo ""
echo "✅ Certificate Generated:"
echo "   Certificate: /etc/ssl/certs/zinusit.crt"
echo "   Private Key: /etc/ssl/private/zinusit.key"
echo ""
echo "⚠️  NOTE: Browser akan warning 'Not Secure' karena self-signed."
echo "   Klik 'Advanced' -> 'Proceed to site' untuk bypass."
echo ""
echo "📋 Next steps:"
echo "   1. Update nginx config dengan certificate path"
echo "   2. sudo nginx -t"
echo "   3. sudo systemctl restart nginx"
echo ""
