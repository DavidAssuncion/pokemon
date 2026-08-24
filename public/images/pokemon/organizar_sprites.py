import os
import shutil
import re

# Recorre tanto la raíz como la carpeta 'shiny' por si ya hay archivos dentro
def clasificar_y_mover():
    source_dir = "."

    # Expresiones regulares para detectar etiquetas sin importar el orden
    pattern_mega = re.compile(r"_e\d+")
    pattern_regional = re.compile(r"_g\d+")
    pattern_shadow = re.compile(r"_b\d+")
    pattern_accessory = re.compile(r"_(a|c)\d+")
    pattern_form = re.compile(r"(_f\d+|_FORM)")
    pattern_shiny = re.compile(r"_s(\.png|$)")

    for root, dirs, files in os.walk(source_dir):
        # Evitar entrar en carpetas de destino que ya estén bien organizadas si se prefiere, 
        # pero es mejor revisar todo el directorio.
        for filename in files:
            if not filename.endswith(".png"):
                continue

            name_without_ext = filename[:-4]

            # Detección de etiquetas
            is_shiny = bool(pattern_shiny.search(filename)) or "shiny" in root
            has_mega = bool(pattern_mega.search(name_without_ext))
            has_regional = bool(pattern_regional.search(name_without_ext))
            has_shadow = bool(pattern_shadow.search(name_without_ext))
            has_accessory = bool(pattern_accessory.search(name_without_ext))
            has_form = bool(pattern_form.search(name_without_ext))

            # Definir subcarpeta según jerarquía de importancia
            subfolder = ""
            if has_mega:
                subfolder = "megas_gigantamax"
            elif has_regional:
                subfolder = "regionales"
            elif has_shadow:
                subfolder = "shadow_purificado"
            elif has_accessory:
                subfolder = "accesorios"
            elif has_form:
                subfolder = "formas"

            # Determinar ruta final
            if is_shiny:
                if subfolder:
                    target_dir = os.path.join("shiny", subfolder)
                else:
                    target_dir = "shiny"
            else:
                if subfolder:
                    target_dir = subfolder
                else:
                    # Es una imagen base normal sin atributos -> se queda en la raíz
                    target_dir = "."

            # Ruta actual del archivo
            current_path = os.path.join(root, filename)
            # Ruta de destino esperada
            target_path = os.path.join(target_dir, filename)

            # Si el archivo no está en su sitio correcto, moverlo
            if os.path.abspath(current_path) != os.path.abspath(target_path):
                os.makedirs(target_dir, exist_ok=True)
                shutil.move(current_path, target_path)
                print(f"[MOVIDO] {filename} -> {target_dir}/")

if __name__ == "__main__":
    clasificar_y_mover()
    print("\n¡Clasificación Shiny y Formas combinadas completada con éxito!")