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

<button id="print-button" onclick="window.print()" title="Imprimir certificado">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
        <path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/>
    </svg>
    <span>Imprimir</span>
</button>
