#!/usr/bin/env bash

rm -rf .git
rm src/.gitkeep
rm tests/.gitkeep
rm README.md
touch README.md
git init
git add .
git commit -m 'First Commit.'

rm init.sh

