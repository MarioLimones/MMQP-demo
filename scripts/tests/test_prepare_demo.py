"""Installer isolation checks; the artisan stub records the invoked child environment.

Run: python -m unittest discover -s scripts/tests -v
Set MMQP_DEMO_TEST_PHP if PHP is not on PATH.
"""

from pathlib import Path
import json
import os
import shutil
import subprocess
import tempfile
import unittest


INSTALLER = Path(__file__).resolve().parents[1] / "prepare-demo.php"
PHP = os.environ.get("MMQP_DEMO_TEST_PHP") or shutil.which("php")


@unittest.skipUnless(PHP, "Set MMQP_DEMO_TEST_PHP or put PHP on PATH")
class PrepareDemoTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="mmqp-demo-installer-")
        self.root = Path(self.temp.name).resolve()
        self.hub = self.root / "mmqp_hub"
        (self.root / "scripts").mkdir()
        (self.hub / "database").mkdir(parents=True)
        (self.hub / "vendor").mkdir()
        shutil.copyfile(INSTALLER, self.root / "scripts/prepare-demo.php")
        (self.hub / ".env.example").write_text(
            "APP_KEY=\nDEMO_SETUP=1\nDEMO_MODE=true\nDB_CONNECTION=sqlite\n"
            "DB_DATABASE=database/demo.sqlite\nDB_URL=\n",
            encoding="utf-8",
        )
        (self.hub / "vendor/autoload.php").write_text("<?php\n", encoding="utf-8")
        (self.hub / "artisan").write_text(
            "<?php\n"
            "$entry = ['args' => array_slice($argv, 1), 'db' => getenv('DB_DATABASE'), "
            "'connection' => getenv('DB_CONNECTION'), 'url' => getenv('DB_URL'), "
            "'demo' => getenv('DEMO_MODE'), 'cache' => getenv('APP_CONFIG_CACHE'), "
            "'storage' => getenv('LARAVEL_STORAGE_PATH'), 'cache_store' => getenv('CACHE_STORE')];\n"
            "file_put_contents(__DIR__.'/invocations.jsonl', json_encode($entry).PHP_EOL, FILE_APPEND);\n",
            encoding="utf-8",
        )

    def tearDown(self):
        self.temp.cleanup()

    def install(self):
        child_env = os.environ.copy()
        child_env.update({
            "DB_CONNECTION": "pgsql",
            "DB_DATABASE": "do-not-touch-operational-database",
            "DB_URL": "postgres://invalid.example/operational",
            "DEMO_MODE": "false",
            "LARAVEL_STORAGE_PATH": str(self.root / "operational-storage-do-not-touch"),
            "CACHE_STORE": "redis",
        })
        return subprocess.run(
            [PHP, str(self.root / "scripts/prepare-demo.php")],
            cwd=self.root,
            env=child_env,
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
        )

    def assert_not_invoked(self):
        self.assertFalse((self.hub / "invocations.jsonl").exists())

    def test_fresh_install_overrides_inherited_external_database(self):
        result = self.install()
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertTrue((self.hub / "database/demo.sqlite").is_file())
        env = (self.hub / ".env").read_text(encoding="utf-8")
        self.assertIn("APP_KEY=base64:", env)
        entries = [json.loads(line) for line in (self.hub / "invocations.jsonl").read_text().splitlines()]
        self.assertEqual([e["args"][0] for e in entries], ["migrate", "db:seed"])
        for entry in entries:
            self.assertEqual(entry["db"], (self.hub / "database/demo.sqlite").as_posix())
            self.assertEqual(entry["connection"], "sqlite")
            self.assertEqual(entry["url"], "")
            self.assertEqual(entry["demo"], "true")
            self.assertEqual(Path(entry["storage"]), self.hub / "storage")
            self.assertEqual(entry["cache_store"], "file")
            self.assertTrue((self.hub / entry["cache"]).resolve().is_relative_to(self.hub))

    def test_repeat_keeps_app_key_and_database_bytes(self):
        self.assertEqual(self.install().returncode, 0)
        initial_env = (self.hub / ".env").read_bytes()
        database = self.hub / "database/demo.sqlite"
        database.write_bytes(b"existing-demo-edits")
        result = self.install()
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertEqual((self.hub / ".env").read_bytes(), initial_env)
        self.assertEqual(database.read_bytes(), b"existing-demo-edits")

    def test_refuses_unmarked_environment_before_migrations(self):
        env = self.hub / ".env"
        original = "DB_CONNECTION=sqlite\nDB_DATABASE=production.sqlite\n"
        env.write_text(original, encoding="utf-8")
        self.assertNotEqual(self.install().returncode, 0)
        self.assertEqual(env.read_text(encoding="utf-8"), original)
        self.assert_not_invoked()

    def test_refuses_marked_environment_with_external_path_before_migrations(self):
        (self.hub / ".env").write_text(
            "DEMO_SETUP=1\nDEMO_MODE=true\nDB_CONNECTION=sqlite\nDB_DATABASE=/outside/demo.sqlite\n",
            encoding="utf-8",
        )
        self.assertNotEqual(self.install().returncode, 0)
        self.assert_not_invoked()

    def test_refuses_existing_database_without_its_environment(self):
        database = self.hub / "database/demo.sqlite"
        database.write_bytes(b"unknown-database")
        self.assertNotEqual(self.install().returncode, 0)
        self.assertEqual(database.read_bytes(), b"unknown-database")
        self.assertFalse((self.hub / ".env").exists())
        self.assert_not_invoked()

    def test_missing_dependencies_does_not_create_environment_or_database(self):
        (self.hub / "vendor/autoload.php").unlink()
        self.assertNotEqual(self.install().returncode, 0)
        self.assertFalse((self.hub / ".env").exists())
        self.assertFalse((self.hub / "database/demo.sqlite").exists())
        self.assert_not_invoked()


if __name__ == "__main__":
    unittest.main()
