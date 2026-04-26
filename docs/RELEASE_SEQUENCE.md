# Recommended release sequence

1. **Tag and back up first**
   - Export a full backup package before changing schema or storage paths.

2. **Treat schema changes as migrations**
   - Stop using drop-and-recreate as the normal workflow.
   - Every structural change should be versioned and reversible where practical.

3. **Separate environment labels from code**
   - Use `APP_MODE`, `APP_BETA_BANNER_ENABLED`, and `APP_BETA_BANNER_LABEL`.
   - This makes the disclaimer removable without editing every page.

4. **Keep feedback in-product**
   - `feedback.php` captures bugs and feature requests during dev and beta.

5. **Promote sandbox -> beta intentionally**
   - Use the phone sandbox for experimentation.
   - Promote tested changes to the beta package after backup and smoke testing.
