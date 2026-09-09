set -e
cd /volume1/kitel_web/xe

rm -rf rental_demo
cp -r rental_dev rental_demo
cp /tmp/demo_config.php rental_demo/app/config.php
cp /tmp/demo_bootstrap.php rental_demo/app/bootstrap.php
cp /tmp/local_data.json rental_demo/database/local_data.json

chmod -R a+rX rental_demo
chmod -R 777 rental_demo/database

echo "=== 배치 결과 ==="
ls -la rental_demo/ | grep -E "app$|database$|public$|README"
echo "--- config mode / host ---"
grep -E "'mode'|allow_local_http_hosts|require_trusted_network" rental_demo/app/config.php
echo "--- bootstrap session ---"
grep -n "session_name\|session_start" rental_demo/app/bootstrap.php
echo "--- seed ---"
ls -la rental_demo/database/local_data.json
echo "--- php74 lint (전체) ---"
fail=0
for f in $(find rental_demo -name "*.php"); do
  /usr/local/bin/php74 -l "$f" >/dev/null 2>&1 || { fail=$((fail+1)); echo "  FAIL $f"; }
done
echo "syntax errors: $fail"
