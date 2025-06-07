#!/bin/sh

ITERATION=1

while true; do
  #echo "🔁 Rector iteration: $ITERATION"

  OUTPUT=$(./vendor/bin/rector process --no-progress-bar --ansi 2>&1)

  echo "$OUTPUT"

  echo "$OUTPUT" | grep -q '\[OK\] [1-9][0-9]* files have been changed by Rector'
  CHANGED=$?

  echo "$OUTPUT" | grep -q '\[OK\] 1 file has been changed by Rector'
  CHANGED_SINGLE=$?

  if [ $CHANGED -ne 0 ] && [ $CHANGED_SINGLE -ne 0 ]; then
    #echo "✅ Rector is done!"
    break
  fi

  ITERATION=$(expr $ITERATION + 1)

  if [ $ITERATION -gt 10 ]; then
    #echo "⚠️ The maximum number of iterations (10) has been exceeded. Abort."
    break
  fi
done
