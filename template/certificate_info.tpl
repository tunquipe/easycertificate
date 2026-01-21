<style>
    @media print {
        .alert {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }

        .alert-success {
            background-color: #d4edda !important;
            border-color: #c3e6cb !important;
            color: #155724 !important;
        }
    }
</style>
<div class="container">
    <div class="panel panel-default">
        <div class="panel-body">
            <div class="text-center logo">
                <img src="{{_p.web_css_theme}}images/header-logo.png" alt="">
            </div>
            <div class="header-search">
                <h3>{{ 'RegisterOfGeneratedCertificates'|get_plugin_lang('EasyCertificatePlugin') }} - {{ certificate.username }}</h3>
            </div>
            {% if certificate %}
            <div class="alert alert-success">
                <strong>{{ 'CertificateFound'|get_plugin_lang('EasyCertificatePlugin') }}</strong>
            </div>
            <!--<div class="icon">
                <img width="100px" src="{{ _p.web }}plugin/easycertificate/resources/img/svg/happy.svg">
            </div>-->
            <div class="name-certificate">
                <h5>{{ 'studentName'|get_plugin_lang('EasyCertificatePlugin') }}</h5>
                <h3>{{ certificate.studentName }}</h3>
            </div>
            <div class="description">
                <dl>
                    <dt>
                        {{ 'courseName'|get_plugin_lang('EasyCertificatePlugin') }}
                    </dt>
                    <dd>
                        {{ certificate.courseName }}
                    </dd>
                    <dt>
                        {{ 'datePrint'|get_plugin_lang('EasyCertificatePlugin') }}
                    </dt>
                    <dd>
                        {{ certificate.datePrint }}
                    </dd>
                    <dt>
                        {{ 'ExpirationSession'|get_plugin_lang('EasyCertificatePlugin') }}
                    </dt>
                    <dd>
                        {{ certificate.expiration_date }}
                    </dd>
                    <dt>
                        {{ 'scoreCertificate'|get_plugin_lang('EasyCertificatePlugin') }}
                    </dt>
                    <dd>
                        {{ certificate.scoreCertificate }}
                    </dd>
                    <dt>
                        {{ 'codeCertificate'|get_plugin_lang('EasyCertificatePlugin') }}
                    </dt>
                    <dd class="code-cert">
                        PROIKOS-{{ certificate.proikosCertCode }}
                    </dd>
                    <dd class="code-cert">
                        {{ certificate.urlBarCode }}
                    </dd>
                    <dd class="code-cert">
                        {{ certificate.codeCertificate }}
                    </dd>
                </dl>
            </div>
            {% else %}
                <div class="alert alert-warning">
                    <strong>{{ 'NoCertificate'|get_plugin_lang('EasyCertificatePlugin') }}</strong>
                </div>
            <!--
                <div class="icon">
                    <img width="100px" src="{{ _p.web }}plugin/easycertificate/resources/img/svg/sad.svg">
                </div> -->
            {% endif %}
            <div class="alert alert-info" role="alert">
                {{ 'ErrorInTheRegisteredCertificate'|get_plugin_lang('EasyCertificatePlugin') }}
            </div>
            {% if certificate.url_certificate %}
            <div>
                <br> <strong>URL de validacion: </strong>
                <a href="{{ certificate.url_certificate }}" target="_blank">{{ certificate.url_certificate }}</a>
                <p><br> <strong>Descargar certificado: </strong><a href="{{ certificate.url_download }}" target="_blank">{{ certificate.url_download }}</a></p>
            </div>
            {% endif %}
        </div>
    </div>
</div>