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

public class NoEscapeSecurityManager extends SecurityManager {

	@Override
	public void checkExit(int status) {
		super.checkExit(status);

		StackTraceElement[] stackTrace = Thread.currentThread().getStackTrace();
		for (int i = 0; i + 1 < stackTrace.length; ++i) {
			if ("java.lang.System".equals(stackTrace[i].getClassName()) && "exit".equals(stackTrace[i].getMethodName())) {
				if (stackTrace[i+1].getClassName().startsWith("org.apache.maven")) {
					// System.exit calls from within Maven code are OK
					return;
				}
			}
		}

		throw new SecurityException("cannot exit during tests");
	}

}
