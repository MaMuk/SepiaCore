#!/bin/bash

# Get the current working directory (where this script is located)
CWD=$(pwd)

# Set default port to 8001 if no argument is provided
PORT=${1:-8001}

# Start backend server
echo "Starting backend server on port $PORT..."
cd "$CWD/backend" && php -S 0.0.0.0:$PORT &

# Wait a moment to ensure the backend server starts
sleep 2

# Start frontend server on PORT+1
FRONTEND_PORT=$((PORT + 1))
echo "Starting frontend server on port $FRONTEND_PORT..."
cd "$CWD/frontend" && php -S 0.0.0.0:$FRONTEND_PORT &

# Print URLs for easy access
echo ""
echo "Servers started successfully!"
echo "Backend: http://localhost:$PORT"
echo "Frontend: http://localhost:$FRONTEND_PORT"
echo ""
echo "Press Ctrl+C to stop both servers"

# Keep the script running to keep both servers alive
wait
