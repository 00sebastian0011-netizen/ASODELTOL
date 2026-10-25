"""Compatibility entry point for the current, report-driven evidence closure."""
import pathlib
import runpy

runpy.run_path(str(pathlib.Path(__file__).with_name('close-local-evidence.py')), run_name='__main__')
