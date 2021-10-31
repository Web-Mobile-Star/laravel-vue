/**
 *  Copyright 2020 Aston University
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

package uk.ac.aston.autofeedback.policy;

import static org.junit.Assert.assertEquals;
import static org.junit.Assert.fail;

import java.io.File;
import java.io.IOException;
import java.net.URL;
import java.security.AccessControlException;
import java.util.Arrays;
import java.util.HashSet;
import java.util.Set;
import java.util.concurrent.TimeUnit;

import org.junit.Test;
import org.junit.Ignore;

public class BadBehaviourTest {

    // Should not be able to run external commands
    @Test(expected = SecurityException.class)
    public void runtimeExec() throws IOException {
        Runtime.getRuntime().exec("/bin/ls");
    }

    // Should not be able to retrieve all environment variables in one go
    @Test(expected = SecurityException.class)
    public void allEnvironmentVariables() {
        System.getenv();
    }

    @Test
    public void sensitiveEnvironmentVariables() {
        /*
         * These are the sensitive environment variables in the recommended
         * Docker Compose files.
         */
        for (String name : Arrays.asList(
               "DB_PASSWORD", "LDAP_PASSWORD",
               "MARIADB_ROOT_PASSWORD", "MARIADB_PASSWORD",
               "REDIS_PASSWORD", "LDAP_ADMIN_PASSWORD")) {
            try {
                System.getenv(name);
                fail("Should not be able to fetch the environment variable " + name);
            } catch (SecurityException ex) {
                // good, this is what we want
            }
        }
    }

    @Ignore // requires custom SecurityManager
    @Test(expected = SecurityException.class)
    public void systemExit() {
        System.exit(1);
        fail("no!");
    }

    // Should not be able to connect to external resources
    @Test(expected = SecurityException.class)
    public void connect() throws Exception {
        new URL("http", "example.com", 80, "/").openConnection().connect();
    }

   // Should not be able to touch files in /home
   @Test(expected = AccessControlException.class)
   public void createHomeFile() throws Exception {
       new File("/home/www-data/foo").createNewFile();
   }

   // Should not be able to touch files in /home/www-data/.m2 (even though Maven itself can write there)
   @Test(expected = AccessControlException.class)
   public void createM2File() throws Exception {
       new File("/home/www-data/.m2/foo").createNewFile();
   }

  /*
   * This test is for manual testing of the timeout feature in a local development environment.
   * Comment out the @Ignore, then zip up the java-policy project and try creating a raw job
   * with this build. It should timeout and fail with a "Failed (9)" message: the 9 is from
   * the SIGKILL Linux signal it should have been sent.
   */
  @Ignore
  @Test
  public void shouldTimeOut() throws Exception {
    System.out.println("Waiting for 15 minutes");
    TimeUnit.MINUTES.sleep(15);
    fail("Should have timed out!");
  }
}
