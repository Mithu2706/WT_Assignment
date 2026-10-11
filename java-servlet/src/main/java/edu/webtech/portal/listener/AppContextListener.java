package edu.webtech.portal.listener;

import com.mysql.cj.jdbc.AbandonedConnectionCleanupThread;
import edu.webtech.portal.util.AppConfig;
import edu.webtech.portal.util.DatabaseUtil;
import edu.webtech.portal.util.StoragePaths;

import javax.servlet.ServletContext;
import javax.servlet.ServletContextEvent;
import javax.servlet.ServletContextListener;
import javax.servlet.annotation.WebListener;
import java.sql.Driver;
import java.sql.DriverManager;
import java.sql.SQLException;
import java.util.Enumeration;
import java.util.logging.Level;
import java.util.logging.Logger;

/**
 * Runs once when Tomcat starts / stops the web application.
 * Start: load the shared .env configuration and prepare the JDBC utility.
 * Stop:  deregister the JDBC driver so redeploying does not leak memory.
 */
@WebListener
public class AppContextListener implements ServletContextListener {

    public static final String STORAGE_ATTR = "storagePaths";
    public static final String CONFIG_ATTR = "appConfig";
    private static final Logger LOG = Logger.getLogger(AppContextListener.class.getName());

    @Override
    public void contextInitialized(ServletContextEvent event) {
        ServletContext ctx = event.getServletContext();
        try {
            AppConfig config = AppConfig.locate(ctx.getInitParameter("smp.config"));
            DatabaseUtil.init(config);
            ctx.setAttribute(CONFIG_ATTR, config);
            ctx.setAttribute(STORAGE_ATTR, new StoragePaths(config.getStorageDir()));
            // Log where settings came from - never the password.
            LOG.info("Student portal configured from " + config.getSource()
                    + " (database " + config.getDbName() + ", storage " + config.getStorageDir() + ")");
        } catch (Exception e) {
            // The app still starts; every request shows a friendly "not configured" page.
            LOG.log(Level.SEVERE, "Student portal configuration failed: " + e.getMessage());
        }
    }

    @Override
    public void contextDestroyed(ServletContextEvent event) {
        ClassLoader webappLoader = Thread.currentThread().getContextClassLoader();
        Enumeration<Driver> drivers = DriverManager.getDrivers();
        while (drivers.hasMoreElements()) {
            Driver driver = drivers.nextElement();
            if (driver.getClass().getClassLoader() == webappLoader) {
                try {
                    DriverManager.deregisterDriver(driver);
                } catch (SQLException e) {
                    LOG.log(Level.WARNING, "Could not deregister JDBC driver", e);
                }
            }
        }
        AbandonedConnectionCleanupThread.checkedShutdown();
    }
}
