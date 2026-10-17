<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Blood Bank Platform API · Swagger UI</title>
    <link rel="stylesheet" href="{{ asset('vendor/swagger-ui/swagger-ui.css') }}">
</head>
<body>
    <div id="swagger-ui" data-spec-url="{{ route('api.docs.spec') }}"></div>
    <script src="{{ asset('vendor/swagger-ui/swagger-ui-bundle.js') }}"></script>
    <script>
        SwaggerUIBundle({
            url: document.getElementById('swagger-ui').dataset.specUrl,
            dom_id: '#swagger-ui',
            deepLinking: true,
            displayRequestDuration: true,
            persistAuthorization: false,
        });
    </script>
</body>
</html>
