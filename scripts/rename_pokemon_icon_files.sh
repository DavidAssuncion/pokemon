#!/usr/bin/env bash

# Renombrar iconos de pokemon desde el patrón original:
#   pmNNNN_AA_BB_CC_big.png
# a este formato:
#   N_AA_BB_CC.png
#
# Esto preserva las formas especiales y elimina los ceros iniciales del ID.
# Ejemplo:
# pm0386_11_00_00_big.png -> 386_11_00_00.png
# pm0007_00_00_00_big.png -> 7_00_00_00.png
#
# También renombra los ficheros ya convertidos con padding:
# 0007_00_00_00.png -> 7_00_00_00.png

set -euo pipefail

DIR="resources/pokemon_icono"
PATTERN='^pm0*([0-9]+)_([0-9]{2}_[0-9]{2}_[0-9]{2})_big\.png$'
PADDED_PATTERN='^0+([0-9]+)_([0-9]{2}_[0-9]{2}_[0-9]{2})\.png$'

if [[ ! -d "$DIR" ]]; then
  echo "Directorio no encontrado: $DIR" >&2
  exit 1
fi

shopt -s nullglob

echo "Renombrando archivos pm..."
for fullpath in "$DIR"/pm*_big.png; do
  filename="$(basename "$fullpath")"
  if [[ "$filename" =~ $PATTERN ]]; then
    id="${BASH_REMATCH[1]}"
    suffix="${BASH_REMATCH[2]}"
    if [[ "$suffix" == "00_00_00" ]]; then
      newname="${id}.png"
    else
      newname="${id}_${suffix}.png"
    fi
    newpath="$DIR/$newname"

    if [[ -e "$newpath" ]]; then
      echo "Omitiendo: $filename -> $newname (ya existe)"
      continue
    fi

    echo "Renombrando: $filename -> $newname"
    mv -- "$fullpath" "$newpath"
  else
    echo "No coincide, omitiendo: $filename"
  fi
done

echo "Renombrando archivos con padding de ID..."
for fullpath in "$DIR"/0*_??_??_??.png; do
  filename="$(basename "$fullpath")"
  if [[ "$filename" =~ $PADDED_PATTERN ]]; then
    id="${BASH_REMATCH[1]}"
    suffix="${BASH_REMATCH[2]}"
    if [[ "$suffix" == "00_00_00" ]]; then
      newname="${id}.png"
    else
      newname="${id}_${suffix}.png"
    fi
    newpath="$DIR/$newname"

    if [[ -e "$newpath" ]]; then
      echo "Omitiendo: $filename -> $newname (ya existe)"
      continue
    fi

    echo "Renombrando: $filename -> $newname"
    mv -- "$fullpath" "$newpath"
  else
    echo "No coincide, omitiendo: $filename"
  fi
done

echo "Eliminando versiones femeninas..."
for fullpath in "$DIR"/*_01_*.png; do
  filename="$(basename "$fullpath")"
  echo "Eliminando: $filename"
  rm -- "$fullpath"
done

echo "Normalizando archivos 00_00_00 a id.png..."
for fullpath in "$DIR"/*_00_00_00.png; do
  filename="$(basename "$fullpath")"
  if [[ "$filename" =~ ^([0-9]+)_00_00_00\.png$ ]]; then
    newname="${BASH_REMATCH[1]}.png"
    newpath="$DIR/$newname"
    if [[ "$filename" != "$newname" ]]; then
      if [[ -e "$newpath" ]]; then
        echo "Omitiendo: $filename -> $newname (ya existe)"
        continue
      fi
      echo "Renombrando: $filename -> $newname"
      mv -- "$fullpath" "$newpath"
    fi
  else
    echo "No coincide, omitiendo: $filename"
  fi
done

shopt -u nullglob

echo "Renombrado completado."
