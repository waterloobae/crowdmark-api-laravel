<script>
    let content = `{_Head}`;

    if (!document.head) {
        const head = document.createElement('head');
        document.documentElement.prepend(head);
    } 

    const headEnd = document.head.querySelector('head > :last-child');
    if (headEnd) {
        headEnd.insertAdjacentHTML('afterend', content);
    } else {
        document.head.insertAdjacentHTML('beforeend', content);
    }
    
    function insertScriptBeforeHead({ src = '', type = '', content = '' } = {}) {
        let script = document.createElement('script');

        if (type) {
            script.type = type;
        }

        if (src) {
            script.src = src;
            script.async = true; // Ensures non-blocking loading
        } else if (content) {
            script.textContent = content;
        }

        let head = document.getElementsByTagName('head')[0];
        head.appendChild(script);
    };

    // Example usage:
    insertScriptBeforeHead({ 
        src: '', 
        type: 'importmap', 
        content: '{ "imports": { "@material/web/": "https://esm.run/@material/web/" } }'
    });

    insertScriptBeforeHead({ 
        src: '',
        type: 'module',
        content: "import '@material/web/all.js'; import {styles as typescaleStyles} from '@material/web/typography/md-typescale-styles.js'; document.adoptedStyleSheets.push(typescaleStyles.styleSheet);"
    });
</script>
