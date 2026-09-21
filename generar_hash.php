<?php
/**
 * Script temporal para generar contraseñas hasheadas
 * IMPORTANTE: Eliminar este archivo después de usarlo
 */

// La contraseña que quieres hashear
$password = "123";

// Generar el hash (PASSWORD_DEFAULT usa bcrypt, el más seguro actualmente)
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Contraseña original: " . $password . "<br>";
echo "Hash generado: " . $hash . "<br>";

// Verificar que funciona
if (password_verify($password, $hash)) {
    echo "✅ Verificación exitosa";
} else {
    echo "❌ Error en la verificación";
}
?>