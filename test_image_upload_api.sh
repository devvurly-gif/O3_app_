#!/bin/bash

# Test image upload via API
echo "Testing Image Upload API"
echo "========================"
echo ""

# Create a test image
TESTIMG="test_upload.png"
convert -size 100x100 xc:blue "$TESTIMG" 2>/dev/null || php -r "
\$img = imagecreatetruecolor(100, 100);
\$color = imagecolorallocate(\$img, 0, 0, 255);
imagefill(\$img, 0, 0, \$color);
imagepng(\$img, '$TESTIMG');
imagedestroy(\$img);
"

# Upload to product 1
echo "Uploading image to product 1..."
curl -X POST http://localhost/api/products/1/images \
  -F "image=@$TESTIMG" \
  -F "isPrimary=1" \
  -H "Accept: application/json" \
  2>/dev/null | jq '.' 2>/dev/null || echo "Upload response received"

echo ""
echo "✓ Test complete"

# Cleanup
rm -f "$TESTIMG"
