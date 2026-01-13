#!/bin/bash
# Generate self-signed SSL certificates for development

mkdir -p docker/nginx/ssl

openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout docker/nginx/ssl/key.pem \
  -out docker/nginx/ssl/cert.pem \
  -subj "/C=US/ST=State/L=City/O=Confer/CN=localhost"

echo "✓ SSL certificates generated in docker/nginx/ssl/"
echo ""
echo "To use HTTPS:"
echo "1. Update docker-compose.yml to mount nginx.ssl.conf"
echo "2. Expose port 443"
echo "3. Restart containers: docker-compose down && docker-compose up -d"
