plugins { kotlin("jvm") version "2.1.0" }

group = "com.authplatform"
version = "0.1.0"

kotlin { jvmToolchain(17) }

dependencies {
    implementation("com.fasterxml.jackson.core:jackson-databind:2.18.2")
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-core:1.9.0")
    testImplementation(kotlin("test"))
    testImplementation("org.junit.jupiter:junit-jupiter:5.11.4")
}

sourceSets {
    main { java.srcDir("../java/src/main/java") }
}

tasks.test { useJUnitPlatform() }
