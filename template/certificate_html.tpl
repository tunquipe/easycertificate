{% if orientation == 'h' %}
{% set page_width = '29.7cm' %}
{% set page_height = '21cm' %}
{% set bg_image = background_h %}
{% set data_orientation = 'h' %}
{% else %}
{% set page_width = '21cm' %}
{% set page_height = '29.7cm' %}
{% set bg_image = background_v %}
{% set data_orientation = 'v' %}
{% endif %}

<div id="page-a" data-orientation="{{ data_orientation }}" style="
{% if bg_image %}background-image: url('{{ bg_image }}');{% endif %}
        background-size: cover;
        background-repeat: no-repeat;
        background-position: center;
        width: {{ page_width }};
        height: {{ page_height }};
        ">
    <div style="
            width: 100%;
            height: 100%;
            padding: {{ margin }};
            box-sizing: border-box;
            position: relative;
            ">
        {{ front_content|raw }}
    </div>
</div>

{% if show_back %}
<div id="page-b" data-orientation="{{ data_orientation }}" style="
{% if bg_image %}background-image: url('{{ bg_image }}');{% endif %}
        background-size: cover;
        background-repeat: no-repeat;
        background-position: center;
        width: {{ page_width }};
        height: {{ page_height }};
        ">
    <div style="
            width: 100%;
            height: 100%;
            padding: {{ margin }};
            box-sizing: border-box;
            position: relative;
            ">
        {{ back_content|raw }}
    </div>
</div>
{% endif %}

<div id="certificate-actions">
    <button id="print-button" onclick="window.print()" title="Imprimir certificado">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/>
        </svg>
        <span>Imprimir</span>
    </button>

    {% if enable_pdf_download %}
    <button id="download-pdf-button" onclick="downloadCertificatePDF()" title="Descargar PDF">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="white">
            <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
        </svg>
        <span>Descargar PDF</span>
    </button>
    {% endif %}
</div>

{% if enable_pdf_download %}
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
function downloadCertificatePDF() {
    var btn = document.getElementById('download-pdf-button');
    var originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="white" style="animation: spin 1s linear infinite;"><path d="M12 4V2A10 10 0 0 0 2 12h2a8 8 0 0 1 8-8z"/></svg><span>Generando...</span>';
    btn.style.opacity = '0.7';

    var style = document.createElement('style');
    style.textContent = '@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
    document.head.appendChild(style);

    var pageA = document.getElementById('page-a');
    var pageB = document.getElementById('page-b');
    var orientation = pageA.getAttribute('data-orientation');
    var isHorizontal = orientation === 'h';

    var pdfOrientation = isHorizontal ? 'l' : 'p';
    var pdfFormat = 'a4';

    var scale = 2;

    // Ocultar botones durante la captura
    var actionsDiv = document.getElementById('certificate-actions');
    actionsDiv.style.display = 'none';

    var html2canvasOptions = {
        scale: scale,
        useCORS: true,
        allowTaint: false,
        backgroundColor: null,
        logging: false
    };

    // Convertir todas las imagenes a base64 antes de capturar
    convertImagesToBase64(pageA).then(function() {
        var promiseB = pageB ? convertImagesToBase64(pageB) : Promise.resolve();
        return promiseB;
    }).then(function() {
        return html2canvas(pageA, html2canvasOptions);
    }).then(function(canvasA) {
        var { jsPDF } = window.jspdf;
        var pdf = new jsPDF(pdfOrientation, 'mm', pdfFormat);

        var pdfWidth = pdf.internal.pageSize.getWidth();
        var pdfHeight = pdf.internal.pageSize.getHeight();

        var imgDataA = canvasA.toDataURL('image/jpeg', 0.95);
        pdf.addImage(imgDataA, 'JPEG', 0, 0, pdfWidth, pdfHeight);

        if (pageB) {
            return html2canvas(pageB, html2canvasOptions).then(function(canvasB) {
                pdf.addPage(pdfFormat, pdfOrientation);
                var imgDataB = canvasB.toDataURL('image/jpeg', 0.95);
                pdf.addImage(imgDataB, 'JPEG', 0, 0, pdfWidth, pdfHeight);
                return pdf;
            });
        }
        return pdf;
    }).then(function(pdf) {
        pdf.save('certificado.pdf');
        restoreButton();
    }).catch(function(err) {
        console.error('Error al generar PDF:', err);
        alert('Hubo un error al generar el PDF. Intente nuevamente.');
        restoreButton();
    });

    function restoreButton() {
        btn.innerHTML = originalText;
        btn.disabled = false;
        btn.style.opacity = '1';
        actionsDiv.style.display = 'flex';
        style.remove();
    }
}

/**
 * Convierte todas las imagenes (src e background-image) dentro de un elemento a base64
 * para evitar problemas de CORS con html2canvas
 */
function convertImagesToBase64(container) {
    var promises = [];

    // Convertir background-image del contenedor y sus hijos
    var allElements = [container].concat(Array.from(container.querySelectorAll('*')));
    allElements.forEach(function(el) {
        var bgImage = window.getComputedStyle(el).backgroundImage;
        if (bgImage && bgImage !== 'none') {
            var urlMatch = bgImage.match(/url\(["']?(.*?)["']?\)/);
            if (urlMatch && urlMatch[1] && !urlMatch[1].startsWith('data:')) {
                var bgUrl = urlMatch[1];
                promises.push(
                    imageToBase64(bgUrl).then(function(base64) {
                        el.style.backgroundImage = "url('" + base64 + "')";
                    }).catch(function() {
                        // Si falla, mantener la imagen original
                    })
                );
            }
        }
    });

    // Convertir imagenes <img>
    var images = container.querySelectorAll('img');
    images.forEach(function(img) {
        if (img.src && !img.src.startsWith('data:')) {
            promises.push(
                imageToBase64(img.src).then(function(base64) {
                    img.src = base64;
                }).catch(function() {
                    // Si falla, mantener la imagen original
                })
            );
        }
    });

    return Promise.all(promises);
}

/**
 * Carga una imagen desde URL y la convierte a base64
 */
function imageToBase64(url) {
    return new Promise(function(resolve, reject) {
        var img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = function() {
            var canvas = document.createElement('canvas');
            canvas.width = img.naturalWidth;
            canvas.height = img.naturalHeight;
            var ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0);
            try {
                resolve(canvas.toDataURL('image/png'));
            } catch(e) {
                reject(e);
            }
        };
        img.onerror = reject;
        img.src = url + (url.indexOf('?') === -1 ? '?' : '&') + '_t=' + Date.now();
    });
}
</script>
{% endif %}
