# Overlays controlados

Este directorio contiene divergencias mínimas, reproducibles y verificadas frente a una versión oficial de Moodle. No contiene el núcleo completo, `vendor`, `config.php`, datos, respaldos ni secretos.

Cada overlay debe identificar el commit oficial de origen, fijar sus dependencias, incluir hashes SHA-256 y documentar construcción y reversión. Nunca se aplica archivo por archivo sobre una instalación activa: se usa para construir y probar una raíz nueva.
