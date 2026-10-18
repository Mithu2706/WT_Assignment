package com.studentportal.util;

import com.mysql.cj.jdbc.AbandonedConnectionCleanupThread;

import javax.servlet.ServletContextEvent;
import javax.servlet.ServletContextListener;
import java.sql.Driver;
import java.sql.DriverManager;
import java.sql.SQLException;
import java.util.Enumeration;

/**
 * AppLifecycleListener – registered in web.xml.
 * When Tomcat stops or redeploys the application, it unregisters the MySQL
 * JDBC driver and stops Connector/J's background clean-up thread, so no
 * memory leak warnings are produced.
 */
public class AppLifecycleListener implements ServletContextListener {

    @Override
    public void contextInitialized(ServletContextEvent event) {
        event.getServletContext().log("Student Portal started.");
    }

    @Override
    public void contextDestroyed(ServletContextEvent event) {
        ClassLoader appClassLoader = Thread.currentThread().getContextClassLoader();
        Enumeration<Driver> drivers = DriverManager.getDrivers();
        while (drivers.hasMoreElements()) {
            Driver driver = drivers.nextElement();
            if (driver.getClass().getClassLoader() == appClassLoader) {
                try {
                    DriverManager.deregisterDriver(driver);
                } catch (SQLException e) {
                    event.getServletContext().log("Could not deregister JDBC driver", e);
                }
            }
        }
        AbandonedConnectionCleanupThread.checkedShutdown();
    }
}
