@echo off
REM =====================================================================
REM  build.bat - builds target\student-portal.war WITHOUT Maven
REM  (uses only javac + jar from the JDK and the jars in the lib\ folder)
REM
REM  Usage:   build.bat            -> build the WAR
REM           build.bat deploy     -> build and copy the WAR to Tomcat\webapps
REM
REM  Tomcat location: set CATALINA_HOME first if Tomcat is not in C:\xampp\tomcat
REM     set CATALINA_HOME=C:\apache-tomcat-9.0.xx
REM  If you have Maven installed you can use "mvn clean package" instead.
REM =====================================================================
setlocal
cd /d "%~dp0"

if "%CATALINA_HOME%"=="" set "CATALINA_HOME=C:\xampp\tomcat"
set "SERVLET_API=%CATALINA_HOME%\lib\servlet-api.jar"
if not exist "%SERVLET_API%" (
    echo [ERROR] servlet-api.jar not found at "%SERVLET_API%".
    echo         Set CATALINA_HOME to your Tomcat folder and run again.
    exit /b 1
)

where javac >nul 2>nul || (echo [ERROR] javac not found. Install a JDK and add it to PATH. & exit /b 1)

set "OUT=target\student-portal"
echo [1/4] Cleaning target folder...
if exist target rmdir /s /q target
mkdir "%OUT%\WEB-INF\classes" "%OUT%\WEB-INF\lib"

echo [2/4] Compiling Java sources...
dir /s /b src\main\java\*.java > target\sources.txt
javac -encoding UTF-8 -source 8 -target 8 -Xlint:-options ^
      -cp "%SERVLET_API%;lib\*" ^
      -d "%OUT%\WEB-INF\classes" @target\sources.txt
if errorlevel 1 (echo [ERROR] Compilation failed. & exit /b 1)

echo [3/4] Copying web files, db.properties and libraries...
xcopy /e /i /q /y src\main\webapp "%OUT%" >nul
copy /y src\main\resources\db.properties "%OUT%\WEB-INF\classes\" >nul
copy /y lib\*.jar "%OUT%\WEB-INF\lib\" >nul

echo [4/4] Packaging WAR...
REM A WAR file is a ZIP archive. Use the JDK "jar" tool if available,
REM otherwise Windows' built-in tar (bsdtar) in zip mode.
set "JAR_TOOL="
if defined JAVA_HOME if exist "%JAVA_HOME%\bin\jar.exe" set "JAR_TOOL=%JAVA_HOME%\bin\jar.exe"
if not defined JAR_TOOL for /f "delims=" %%J in ('where jar 2^>nul') do if not defined JAR_TOOL set "JAR_TOOL=%%J"
if defined JAR_TOOL (
    "%JAR_TOOL%" -cf target\student-portal.war -C "%OUT%" .
) else (
    tar --format=zip -cf target\student-portal.war -C "%OUT%" .
)
if errorlevel 1 (echo [ERROR] Packaging failed. & exit /b 1)
echo Built target\student-portal.war

if /i "%~1"=="deploy" (
    copy /y target\student-portal.war "%CATALINA_HOME%\webapps\" >nul
    echo Deployed to %CATALINA_HOME%\webapps\student-portal.war
    echo Open http://localhost:8080/student-portal/
)
endlocal
