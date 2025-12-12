cd /c/MAMP/htdocs/student
BASE=http://localhost/student
UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36'
JAR=curl/cookies.txt
HTML=out-test-post.html

curl -s -i -c "$JAR" -A "$UA" "$BASE/test-post.php" -o "$HTML"

for i in {1..5}; do
  # セッションで新トークンを再セット
  curl -s -i -b "$JAR" -A "$UA" "$BASE/test-post.php" -o "$HTML"

  INVALID_TOKEN=$(openssl rand -hex 32)
  echo "INVALID_TOKEN=$INVALID_TOKEN"

  curl -s -D - -o /dev/null \
    -b "$JAR" \
    -A "$UA" \
    -e "$BASE/test-post.php" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    --data-urlencode "csrf_token=$INVALID_TOKEN" \
    "$BASE/test-basic-safety.php" \
    | awk 'BEGIN{loc=""} /^HTTP\/1\.[01]/{code=$2; line=$0} /^Location:/ {loc=$2} END{print "HTTP:", line; print "Location:", (loc?loc:"<none>")}'
  sleep 1
done