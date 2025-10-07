#!/bin/bash

# GEO AI Setup Script
# This script sets up the development environment for GEO AI plugin

set -e

echo "🚀 GEO AI Setup Script"
echo "======================"
echo ""

# Check for Node.js
if ! command -v node &> /dev/null; then
    echo "❌ Node.js is not installed. Please install Node.js 18+ first."
    exit 1
fi

echo "✓ Node.js found: $(node --version)"

# Check for npm
if ! command -v npm &> /dev/null; then
    echo "❌ npm is not installed."
    exit 1
fi

echo "✓ npm found: $(npm --version)"
echo ""

# Install npm dependencies
echo "📦 Installing npm dependencies..."
npm install

echo ""
echo "📥 Downloading Action Scheduler..."

# Create vendor directory
mkdir -p vendor

# Download Action Scheduler
cd vendor

if [ -d "action-scheduler" ]; then
    echo "✓ Action Scheduler already exists"
else
    echo "Downloading Action Scheduler from WordPress.org..."
    
    # Download latest version
    curl -L -o action-scheduler.zip https://downloads.wordpress.org/plugin/action-scheduler.latest-stable.zip
    
    # Extract
    unzip -q action-scheduler.zip
    
    # Clean up
    rm action-scheduler.zip
    
    echo "✓ Action Scheduler downloaded"
fi

cd ..

echo ""
echo "🔨 Building JavaScript assets..."
npm run build

echo ""
echo "✅ Setup complete!"
echo ""
echo "Next steps:"
echo "1. Copy this plugin to your WordPress plugins directory"
echo "2. Activate the plugin in WordPress admin"
echo "3. Go to Settings → GEO AI and add your Gemini API key"
echo ""
echo "For development:"
echo "- Run 'npm start' for watch mode"
echo "- Run 'npm run build' for production build"
echo ""
echo "Happy coding! 🎉"
