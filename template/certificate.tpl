<!DOCTYPE html>
<head>
    {{ css_certificate }}
</head>
<body style="margin: 0; padding: 0;">
{% if orientation != 'v' %}
    {% if background_h %}
        <div id="page-a" style="background-image: url('{{ background_h }}'); background-size: cover; width: 1200px; height: 793px; position: relative;">
    {% else %}
        <div id="page-a" style="width: 1200px; height: 793px; position: relative;">
    {% endif %}
{% else %}
    {% if background_v %}
        <div id="page-a" style="background-image: url('{{ background_v }}'); background-size: cover; width: 793px; height: 1200px; position: relative;">
    {% else %}
        <div id="page-a" style="width: 793px; height: 1200px; position: relative;">
    {% endif %}
{% endif %}
        <div style="padding: {{ margin }};">
            {{ front_content }}
        </div>
    </div>
    {% if(show_back) %}
        <div id="page-b" class="caraB" style="page-break-before:always; margin:0; padding:2rem;">
            <div style="padding: {{ margin }};">
                {{ back_content }}
            </div>
        </div>
    {% endif %}
</body>
</html>