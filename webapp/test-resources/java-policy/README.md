Java security manager experiments
===

This is a small Java project to test how to block some unwanted behaviours
during tests. System.exit(...) requires a dedicated SecurityManager class,
as done in these experiments. However, since it's only harmful to the test
itself and not to the system, I don't think it's worth the hassle.

Using a dedicated policy with limited permissions is more than enough for
production. It's just one more layer on top of the containerization.
