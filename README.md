EasyCertificate plugin
===============
Este plugin da la posibilidad al administrador de disponer de una herramienta de certificados alternativa
 a la que tiene por defecto la plataforma Chamilo.

**Instrucciones de puesta en funcionamiento**

- Habilitar el plugin en la administraci&oacute;n de Chamilo.
- Indicar 'menu_administrator' en la configuración de la región del plugin.

**accesos a la herramienta**

- Desde la pantalla de Administración para configurar el certificado por defecto.
- Desde las herramientas del curso, para la configuración del diploma especifico.

**Importante a tener en cuenta**

Por defecto los certificados utilizados serán los de la plataforma chamilo. Para habilitar el certificado alternativo
en un curso se debe entrar en la configuración del curso y habilitar en la pestaña de "certificados personalizado" la 
casilla de verificación de "Habilitar en el curso el certificado alternativo".
Si se desea usar el certificado por defecto se deberá mostrar la segunda casilla de verificación.

Requisito minimos para instalacion
-------
Instalar a través de [ compositor ](https://getcomposer.org/doc/00-intro.md):

```
composer require picqer/php-barcode-generator
```

Si desea generar imágenes PNG o JPG, también necesita la biblioteca GD o Imagick instalada en su sistema.



Creditos
-------

- Alex Aragón Calixto (alex.aragon@tunqui.pe)
- Magaly Ancalle

Changelog
-------
**Versión 1.0**

- Genera Codigo de Certificados en MD5
- Trae los extrafields existentes del usuario para usar en el certificado
- Permite añadir un fondo vertical y horizontal

**Versión 2.0**

- Se añadio la opción de integrar un QR por certificado vinculado al codigo de certificado cifrando en MD5
- Permite realizar consultas del certificado via QR en una página de consulta