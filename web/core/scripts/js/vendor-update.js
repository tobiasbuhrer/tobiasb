/**
 * @file
 * Copy files for JS vendor dependencies from node_modules to the assets/vendor
 * folder.
 *
 * This script handles all dependencies except CKEditor, which require a custom
 * build step.
 */

const path = require('node:path');
const { copyFile, writeFile, readFile, chmod, mkdir } = require('node:fs').promises;
const ckeditor5Files = require('./assets/ckeditor5Files');
const jQueryUIProcess = require('./assets/process/jqueryui');
const mapProcess = require('./assets/process/map');

const coreFolder = path.resolve(__dirname, '../../');
const packageFolder = `${coreFolder}/node_modules`;
const assetsFolder = `${coreFolder}/assets/vendor`;

(async () => {
  const librariesPath = `${coreFolder}/core.libraries.yml`;
  // Open the core.libraries.yml file to update version information
  // automatically.
  const libraries = (await readFile(librariesPath)).toString().split('\n\n');

  function updateLibraryVersion(libraryName, { version }) {
    const libraryIndex = libraries.findIndex((lib) =>
      lib.startsWith(libraryName),
    );
    if (libraryIndex > 0) {
      const libraryDeclaration = libraries[libraryIndex];
      // Get the previous package version from the yaml file, versions can be
      // declared with a yaml anchor such as `version: &yaml_anchor "xxx"`
      const currentVersion = libraryDeclaration.match(/version:(?: [&\w_]+)? "(.*)"\n/)[1];
      // Replace the version value and the version in the license URL.
      libraries[libraryIndex] = libraryDeclaration.replace(
        new RegExp(currentVersion, 'g'),
        version,
      );
    }
  }

  /**
   * Structure of the object defining a library to copy to the assets/ folder.
   *
   * @typedef DrupalLibraryAsset
   *
   * @prop {string} pack
   *   The name of the npm package (used to get the name of the folder where
   *   the files are situated inside of the node_modules folder). Note that we
   *   use `pack` and not `package` because `package` is a future reserved word.
   * @prop {string} [folder]
   *   The folder under `assets/vendor/` where the files will be copied. If
   *   this
   *   is not defined the value of `pack` is used.
   * @prop {string} [library]
   *   The key under which the library is declared in core.libraries.yml.
   * @prop {Array} [files]
   *   An array of files to be copied over.
   *     - A string if the file has the same name and is at the same level in
   *   the source and target folder.
   *     - An object with a `from` and `to` property if the source and target
   *   have a different name or if the folder nesting is different.
   * @prop {object} [process]
   *   An object containing a file extension as a key and a callback as the
   *   value. The callback will be called for each file matching the file
   *   extension. It can be used to minify the file content before saving to
   *   the target directory.
   */

  /**
   * Declare the array that defines what needs to be copied over.
   *
   * @type {DrupalLibraryAsset[]}
   */
  const ASSET_LIST = [
    {
      pack: 'backbone',
      library: 'internal.backbone',
      files: ['backbone.js', 'backbone-min.js', 'backbone-min.js.map'],
    },
    {
      pack: 'htmx.org',
      folder: 'htmx',
      library: 'htmx',
      files: [
        { from: 'dist/htmx.min.js', to: 'htmx.min.js' },
        { from: 'dist/htmx.js', to: 'htmx.js' },
      ],
    },
    {
      pack: 'jquery',
      files: [
        { from: 'dist/jquery.js', to: 'jquery.js' },
        { from: 'dist/jquery.min.js', to: 'jquery.min.js' },
        { from: 'dist/jquery.min.map', to: 'jquery.min.map' },
      ],
    },
    {
      pack: 'js-cookie',
      files: [{ from: 'dist/js.cookie.min.js', to: 'js.cookie.min.js' }],
    },
    {
      pack: 'normalize.css',
      folder: 'normalize-css',
      library: 'normalize',
      files: ['normalize.css'],
    },
    {
      pack: '@drupal/once',
      folder: 'once',
      files: [
        { from: 'dist/once.js', to: 'once.js' },
        { from: 'dist/once.min.js', to: 'once.min.js' },
        { from: 'dist/once.min.js.map', to: 'once.min.js.map' },
      ],
    },
    { pack: 'sortablejs', folder: 'sortable', files: ['Sortable.min.js'] },
    {
      pack: 'tabbable',
      files: [
        { from: 'dist/index.umd.min.js', to: 'index.umd.min.js' },
        { from: 'dist/index.umd.min.js.map', to: 'index.umd.min.js.map' },
      ],
    },
    {
      pack: 'underscore',
      library: 'internal.underscore',
      files: ['underscore-min.js', 'underscore-min.js.map'],
    },
    {
      pack: 'loadjs',
      files: [{ from: 'dist/loadjs.min.js', to: 'loadjs.min.js' }],
    },
    {
      pack: 'tua-body-scroll-lock',
      files: [
        { from: 'dist/tua-bsl.umd.min.js', to: 'tua-bsl.umd.min.js' },
      ],
    },
    {
      pack: 'transliteration',
      files: [
        { from: 'dist/browser/bundle.umd.min.js', to: 'bundle.umd.min.js' },
        { from: 'dist/browser/bundle.umd.min.js.map', to: 'bundle.umd.min.js.map' },
      ],
    },
    {
      pack: 'jquery-ui',
      folder: 'jquery.ui',
      process: {
        // This will automatically minify the files and update the destination
        // filename before saving.
        '.js': jQueryUIProcess,
      },
      files: [
        'themes/base/autocomplete.css',
        'themes/base/button.css',
        'themes/base/checkboxradio.css',
        'themes/base/controlgroup.css',
        'themes/base/core.css',
        'themes/base/dialog.css',
        'themes/base/draggable.css',
        'themes/base/images/ui-bg_flat_0_aaaaaa_40x100.png',
        'themes/base/images/ui-icons_444444_256x240.png',
        'themes/base/images/ui-icons_555555_256x240.png',
        'themes/base/images/ui-icons_777620_256x240.png',
        'themes/base/images/ui-icons_777777_256x240.png',
        'themes/base/images/ui-icons_cc0000_256x240.png',
        'themes/base/images/ui-icons_ffffff_256x240.png',
        'themes/base/menu.css',
        'themes/base/resizable.css',
        'themes/base/theme.css',
        'ui/data.js',
        'ui/disable-selection.js',
        'ui/focusable.js',
        'ui/form-reset-mixin.js',
        'ui/jquery-patch.js',
        'ui/keycode.js',
        'ui/labels.js',
        'ui/plugin.js',
        'ui/scroll-parent.js',
        'ui/unique-id.js',
        'ui/version.js',
        'ui/widget.js',
        'ui/widgets/autocomplete.js',
        'ui/widgets/button.js',
        'ui/widgets/checkboxradio.js',
        'ui/widgets/controlgroup.js',
        'ui/widgets/dialog.js',
        'ui/widgets/draggable.js',
        'ui/widgets/menu.js',
        'ui/widgets/mouse.js',
        'ui/widgets/resizable.js',
      ],
    },
    {
      pack: '@floating-ui/dom',
      folder: 'floating-ui',
      library: 'internal.floating-ui',
      files: [
        { from: '../core/dist/floating-ui.core.umd.min.js', to: 'floating-ui.core.umd.min.js' },
        { from: 'dist/floating-ui.dom.umd.min.js', to: 'floating-ui.dom.umd.min.js' },
      ],
    },
    // CKEditor 5 builds the list of files dynamically based on what exists
    // in the filesystem.
    ...ckeditor5Files(packageFolder),
  ];

  /**
   * Default callback for processing map files.
   */
  const defaultProcessCallbacks = {
    '.map': mapProcess,
  };

  /**
   * Return an object with a 'from' and 'to' member.
   *
   * @param {string|object} file
   *
   * @return {{from: string, to: string}}
   */
  function normalizeFile(file) {
    let normalized = file;
    if (typeof file === 'string') {
      normalized = {
        from: file,
        to: file,
      };
    }
    return normalized;
  }

  for (const { pack, files = [], folder = false, library = false, process = {} } of ASSET_LIST) {
    const sourceFolder = pack;
    const libraryName = library || folder || pack;
    const destFolder = folder || pack;
    // Add a callback for map files by default.
    const processCallbacks = { ...defaultProcessCallbacks, ...process };

    // Update the library version in core.libraries.yml with the version
    // from the npm package.
    try {
      const packageInfo = JSON.parse((await readFile(`${packageFolder}/${sourceFolder}/package.json`)).toString());
      updateLibraryVersion(libraryName, packageInfo);
    } catch (e) {
      // The package.json file doesn't exist, so nothing to do.
    }

    for (const file of files.map(normalizeFile)) {
      const sourceFile = `${packageFolder}/${sourceFolder}/${file.from}`;
      const destFile = `${assetsFolder}/${destFolder}/${file.to}`;
      const extension = path.extname(file.from);

      try {
        await mkdir(path.dirname(destFile), { recursive: true });
      } catch (e) {
        // Nothing to do if the folder already exists.
      }

      // There is a callback that transforms the file contents, we are not
      // simply copying a file from A to B.
      if (processCallbacks[extension]) {
        const contents = (await readFile(sourceFile)).toString();
        const results = await processCallbacks[extension]({ file, contents });

        console.log(`Process ${sourceFolder}/${file.from} and save ${results.length} files:\n  ${results.map(({ filename = file.to }) => filename).join(', ')}`);
        for (const { filename = file.to, contents } of results) {
          // The filename key can be used to change the name of the saved file.
          await writeFile(`${assetsFolder}/${destFolder}/${filename}`, contents);
        }
      } else {
        // There is no callback simply copy the file.
        console.log(`Copy ${sourceFolder}/${file.from} to ${destFolder}/${file.to}`);
        await copyFile(sourceFile, destFile);
      }
    }
  }

  await writeFile(librariesPath, libraries.join('\n\n'));
})();
