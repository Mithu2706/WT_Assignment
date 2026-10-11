@echo off
REM ======================================================================
REM  Builds the Servlet module and deploys it to Tomcat (XAMPP by default).
REM
REM    java-servlet\deploy-tomcat.bat                 (uses C:\xampp\tomcat)
REM    set TOMCAT_HOME=D:\apache-tomcat-9.0.x & java-servlet\deploy-tomcat.bat
REM
REM  1. mvnw clean package   -> target\student-portal-servlet.war (runs tests)
REM  2. copies the WAR into %TOMCAT_HOME%\webapps (Tomcat auto-deploys it)
REM  3. if Tomcat has no bin\setenv.bat yet, creates one that points
REM     SMP_CONFIG to this project's .env (never overwrites an existing file)
REM ======================================================================
setlocal
set "TOMCAT=%TOMCAT_HOME%"
if "%TOMCAT%"=="" set "TOMCAT=C:\xampp\tomcat"
for %%I in ("%~dp0..") do set "PROJECT=%%~fI"

if not exist "%PROJECT%\.env" (
    echo [deploy] %PROJECT%\.env not found. Copy .env.example to .env and fill it in first.
    exit /b 1
)
if not exist "%TOMCAT%\webapps" (
    echo [deploy] Tomcat not found at %TOMCAT%. Set TOMCAT_HOME and try again.
    exit /b 1
)

echo [deploy] Building WAR with the Maven wrapper ...
pushd "%~dp0"
call "%~dp0mvnw.cmd" -B -q clean package
set "BUILD_RESULT=%ERRORLEVEL%"
popd
if not "%BUILD_RESULT%"=="0" (
    echo [deploy] Build failed - see the messages above.
    exit /b 1
)

copy /Y "%~dp0target\student-portal-servlet.war" "%TOMCAT%\webapps\" >nul
if errorlevel 1 (
    echo [deploy] Could not copy the WAR to %TOMCAT%\webapps
    exit /b 1
)
echo [deploy] Copied student-portal-servlet.war to %TOMCAT%\webapps

if not exist "%TOMCAT%\bin\setenv.bat" (
    > "%TOMCAT%\bin\setenv.bat" echo @echo off
    >> "%TOMCAT%\bin\setenv.bat" echo rem Created by the Student Portal deploy script: tells the web app where its .env is.
    >> "%TOMCAT%\bin\setenv.bat" echo set "SMP_CONFIG=%PROJECT%\.env"
    echo [deploy] Created %TOMCAT%\bin\setenv.bat with SMP_CONFIG=%PROJECT%\.env
) else (
    findstr /C:"SMP_CONFIG" "%TOMCAT%\bin\setenv.bat" >nul
    if errorlevel 1 (
        echo [deploy] NOTE: %TOMCAT%\bin\setenv.bat exists but does not set SMP_CONFIG.
        echo [deploy]       Add this line to it:  set "SMP_CONFIG=%PROJECT%\.env"
    )
)

echo [deploy] Done. (Re)start Tomcat, then open http://localhost:8080/student-portal-servlet/
endlocal
