#!/bin/bash

# Generate self-signed certificates for development
# This script creates certificates for both frontend and backend

set -e

CERT_DIR="certs"
BACKEND_CERT="$CERT_DIR/backend-cert.pem"
BACKEND_KEY="$CERT_DIR/backend-key.pem"
FRONTEND_CERT="$CERT_DIR/frontend-cert.pem"
FRONTEND_KEY="$CERT_DIR/frontend-key.pem"

# Create certs directory if it doesn't exist
mkdir -p "$CERT_DIR"

echo "Generating self-signed certificates for development..."

# Generate backend certificate (valid for 365 days)
echo "Generating backend certificate..."
openssl req -x509 -newkey rsa:4096 -nodes \
  -keyout "$BACKEND_KEY" \
  -out "$BACKEND_CERT" \
  -days 365 \
  -subj "/C=US/ST=State/L=City/O=Dev/CN=localhost" \
  -addext "subjectAltName=DNS:localhost,DNS:*.localhost,IP:127.0.0.1,IP:::1"

# Generate frontend certificate (valid for 365 days)
echo "Generating frontend certificate..."
openssl req -x509 -newkey rsa:4096 -nodes \
  -keyout "$FRONTEND_KEY" \
  -out "$FRONTEND_CERT" \
  -days 365 \
  -subj "/C=US/ST=State/L=City/O=Dev/CN=localhost" \
  -addext "subjectAltName=DNS:localhost,DNS:*.localhost,IP:127.0.0.1,IP:::1"

# Set permissions
chmod 600 "$BACKEND_KEY" "$FRONTEND_KEY"
chmod 644 "$BACKEND_CERT" "$FRONTEND_CERT"

echo ""
echo "Certificates generated successfully!"
echo ""
echo "Backend certificate: $BACKEND_CERT"
echo "Backend key: $BACKEND_KEY"
echo "Frontend certificate: $FRONTEND_CERT"
echo "Frontend key: $FRONTEND_KEY"
echo ""
echo "To trust these certificates on Manjaro/Arch Linux:"
echo "1. Install ca-certificates if not already installed:"
echo "   sudo pacman -S ca-certificates"
echo ""
echo "2. Copy certificates to system trust store:"
echo "   sudo cp $BACKEND_CERT /usr/local/share/ca-certificates/backend-dev.crt"
echo "   sudo cp $FRONTEND_CERT /usr/local/share/ca-certificates/frontend-dev.crt"
echo "   sudo update-ca-certificates"
echo ""
echo "Or trust them in your browser:"
echo "- Chrome/Edge: Settings > Privacy and Security > Security > Manage certificates > Authorities > Import"
echo "- Firefox: Settings > Privacy & Security > Certificates > View Certificates > Authorities > Import"

