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