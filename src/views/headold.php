<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <script type="importmap">
      {
        "imports": {
          "@material/web/": "https://esm.run/@material/web/"
        }
      }
    </script>
    <script type="module">
      import '@material/web/all.js';
      import {styles as typescaleStyles} from '@material/web/typography/md-typescale-styles.js';
      document.adoptedStyleSheets.push(typescaleStyles.styleSheet);
    </script>    
    <style>
        @import url('../src/css/light.css');
        @import url('../src/css/dashboard.css');
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../src/js/ajax.js"></script>