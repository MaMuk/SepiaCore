#!/bin/bash

# Start PHP backend server with HTTPS using self-signed certificate
# Usage: ./start-https-backend.sh [port]

set -e

# Get the current working directory
CWD=$(pwd)

# Set default port to 8001 if no argument is provided
PORT=${1:-8001}

# Certificate paths
CERT_DIR="$CWD/certs"
CERT_FILE="$CERT_DIR/backend-cert.pem"
KEY_FILE="$CERT_DIR/backend-key.pem"

# Check if certificates exist
if [ ! -f "$CERT_FILE" ] || [ ! -f "$KEY_FILE" ]; then
    echo "Error: Certificates not found!"
    echo "Please run ./generate-dev-certs.sh first to generate certificates."
    exit 1
fi

# Start backend server with HTTPS
echo "Starting backend server with HTTPS on port $PORT..."
echo "Certificate: $CERT_FILE"
echo "Key: $KEY_FILE"
echo ""

cd "$CWD/backend"

# PHP built-in server doesn't natively support HTTPS
# We'll use a workaround with stunnel or use a different approach
# For now, we'll use a simple HTTPS wrapper script

# Alternative: Use stunnel (if installed)
if command -v stunnel &> /dev/null; then
    echo "Using stunnel for HTTPS..."
    # Create stunnel config
    STUNNEL_CONF=$(mktemp)
    cat > "$STUNNEL_CONF" << EOF
cert = $CERT_FILE
key = $KEY_FILE
accept = $PORT
connect = $((PORT + 1000))
EOF
    
    # Start PHP server on internal port
    php -S 0.0.0.0:$((PORT + 1000)) -t . &
    PHP_PID=$!
    
    # Start stunnel
    stunnel "$STUNNEL_CONF" &
    STUNNEL_PID=$!
    
    echo "Backend HTTPS server started!"
    echo "Backend: https://localhost:$PORT"
    echo ""
    echo "Press Ctrl+C to stop"
    
    # Cleanup on exit
    trap "kill $PHP_PID $STUNNEL_PID 2>/dev/null; rm -f $STUNNEL_CONF; exit" INT TERM
    
    wait
else
    echo "Error: stunnel is not installed."
    echo ""
    echo "To install stunnel on Manjaro/Arch:"
    echo "  sudo pacman -S stunnel"
    echo ""
    echo "Or use a different approach:"
    echo "1. Use nginx as reverse proxy with SSL"
    echo "2. Use Caddy server (automatic HTTPS)"
    echo "3. Use a PHP HTTPS wrapper library"
    echo ""
    echo "For now, starting regular HTTP server..."
    php -S 0.0.0.0:$PORT -t .
fi

