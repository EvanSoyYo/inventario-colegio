@echo off
setlocal
if not defined EMSDK_DIR set "EMSDK_DIR=%~dp0..\..\..\work\emsdk"
if not exist "%EMSDK_DIR%\emsdk_env.bat" (
  echo No se encontro Emscripten. Instale emsdk y configure EMSDK_DIR.
  exit /b 1
)
call "%EMSDK_DIR%\emsdk_env.bat"
if errorlevel 1 exit /b 1
if not exist "%~dp0..\assets\wasm" mkdir "%~dp0..\assets\wasm"
emcc "%~dp0inventario.cpp" -O2 -fexceptions -sDISABLE_EXCEPTION_CATCHING=0 -sWASM=1 -sMODULARIZE=1 -sEXPORT_ES6=1 -sEXPORT_NAME=createInventarioModule "-sEXPORTED_FUNCTIONS=['_calcular_dias_atraso','_validar_codigo_equipo','_demostrar_arreglo_poo','_prueba_archivos_virtuales']" "-sEXPORTED_RUNTIME_METHODS=['ccall']" -o "%~dp0..\assets\wasm\inventario.js"
if errorlevel 1 exit /b 1
echo WebAssembly compilado en assets\wasm.
