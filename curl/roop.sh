# ループ処理

# 1から5までの数値を順に処理
# C言語
for i in {1..5}; do
  echo "ループ $i 回目"
done


# 数値の増減を伴うループ処理
for ((i=0; i<10; i++)); do
  echo "$i"
done