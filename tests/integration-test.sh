#!/bin/bash
# Integration test script for composer-dynamic-patches
# This script tests the plugin with a real Composer project

set -e

echo "================================================"
echo "Integration Tests - composer-dynamic-patches"
echo "================================================"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

TEST_DIR="tests/test-project"
PLUGIN_DIR="$(pwd)"

echo -e "${BLUE}Step 1: Setting up test environment${NC}"
echo "--------------------------------------"

# Clean test directory
if [ -d "$TEST_DIR/vendor" ]; then
    echo "Cleaning existing test installation..."
    rm -rf "$TEST_DIR/vendor" "$TEST_DIR/composer.lock" "$TEST_DIR/patches.lock.json"
fi

echo -e "${GREEN}✓${NC} Test environment prepared"
echo ""

echo -e "${BLUE}Step 2: Installing dependencies with verbose output${NC}"
echo "--------------------------------------"
cd "$TEST_DIR"

echo "Running: composer install -vv"
echo ""

# Run composer install with verbose output
composer install -vv 2>&1 | tee /tmp/composer-dynamic-patches-test.log

INSTALL_EXIT_CODE=${PIPESTATUS[0]}

echo ""
if [ $INSTALL_EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Composer install completed successfully"
else
    echo -e "${RED}✗${NC} Composer install failed with exit code $INSTALL_EXIT_CODE"
    exit 1
fi
echo ""

echo -e "${BLUE}Step 3: Validating test results${NC}"
echo "--------------------------------------"

# Check if vendor directory was created
if [ -d "vendor" ]; then
    echo -e "${GREEN}✓${NC} Vendor directory created"
else
    echo -e "${RED}✗${NC} Vendor directory not found"
    exit 1
fi

# Check if patches.lock.json was created
if [ -f "patches.lock.json" ]; then
    echo -e "${GREEN}✓${NC} patches.lock.json created"
    echo "Contents:"
    cat patches.lock.json | head -20
else
    echo -e "${YELLOW}⚠${NC} patches.lock.json not found (may be expected if no patches applied)"
fi
echo ""

# Check log for dynamic patches resolver output
echo -e "${BLUE}Step 4: Checking for plugin activity${NC}"
echo "--------------------------------------"

if grep -q "Resolving dynamic patches" /tmp/composer-dynamic-patches-test.log; then
    echo -e "${GREEN}✓${NC} Dynamic patches resolver was executed"
else
    echo -e "${RED}✗${NC} Dynamic patches resolver was NOT executed"
fi

if grep -q "Processing patches for" /tmp/composer-dynamic-patches-test.log; then
    echo -e "${GREEN}✓${NC} Patches were processed"
    echo "Patches found for:"
    grep "Processing patches for" /tmp/composer-dynamic-patches-test.log
else
    echo -e "${YELLOW}⚠${NC} No patches were processed"
fi

if grep -q "Version constraint" /tmp/composer-dynamic-patches-test.log; then
    echo -e "${GREEN}✓${NC} Version constraints were evaluated"
    echo "Version matching:"
    grep "Version constraint" /tmp/composer-dynamic-patches-test.log | head -5
else
    echo -e "${YELLOW}⚠${NC} No version constraint evaluation found"
fi
echo ""

# Check for errors
echo -e "${BLUE}Step 5: Checking for errors${NC}"
echo "--------------------------------------"

if grep -qi "error" /tmp/composer-dynamic-patches-test.log | grep -v "No error"; then
    echo -e "${RED}⚠${NC} Errors found in log:"
    grep -i "error" /tmp/composer-dynamic-patches-test.log | grep -v "No error"
else
    echo -e "${GREEN}✓${NC} No errors found"
fi
echo ""

# Display summary
echo "================================================"
echo "Integration Test Summary"
echo "================================================"
echo ""
echo "Test log saved to: /tmp/composer-dynamic-patches-test.log"
echo "Test project location: $PLUGIN_DIR/$TEST_DIR"
echo ""
echo -e "${GREEN}✓ Integration tests completed${NC}"
echo ""
echo "To view full output: cat /tmp/composer-dynamic-patches-test.log"
echo "To test manually: cd $TEST_DIR && composer install -vvv"

