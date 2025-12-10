



RESPONSE=$(curl -s -D - -o /dev/null \
  -b "$COOKIE" \
  -A "$UA" \
  -e "$TEST_URL" \
  --data-urlencode "csrf_token=$TOKEN" \
  "$POST_URL")
STATUS=$(echo "$RESPONSE" | head -n1)
LOCATION=$(echo "$RESPONSE" | grep -i '^Location:' | awk '{print $2}')