:: Run easy-coding-standard (ecs) via this batch file inside your IDE e.g. PhpStorm (Windows only)
:: Install inside PhpStorm the  "Batch Script Support" plugin
cd..
cd..
cd..
cd..
cd..
cd..
:: src
vendor\bin\ecs check vendor/markocupic/contao-multifile-download/src --fix --config vendor/markocupic/contao-multifile-download/.ecs/config/default.php
:: tests
vendor\bin\ecs check vendor/markocupic/contao-multifile-download/tests --fix --config vendor/markocupic/contao-multifile-download/.ecs/config/default.php
:: legacy
vendor\bin\ecs check vendor/markocupic/contao-multifile-download/src/Resources/contao --fix --config vendor/markocupic/contao-multifile-download/.ecs/config/legacy.php
:: templates
vendor\bin\ecs check vendor/markocupic/contao-multifile-download/src/Resources/contao/templates --fix --config vendor/markocupic/contao-multifile-download/.ecs/config/template.php
::
cd vendor/markocupic/contao-multifile-download/.ecs./batch/fix
