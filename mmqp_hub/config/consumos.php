<?php

return [
    'pdf_python' => env('CONSUMO_PDF_PYTHON', env('PYTHON_PATH', PHP_OS_FAMILY === 'Windows' ? 'python' : '/opt/venv/bin/python')),
];
