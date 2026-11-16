/** Place server-rendered notices in Moodle's course content and asynchronous course cards. */
(() => {
    const init = () => {
        const course = document.querySelector('template[data-studenthub-notice][data-incourse]');
        if (course) {
            const region = document.querySelector('#region-main');
            region?.prepend(course.content.cloneNode(true));
            return;
        }

        const templates = new Map([...document.querySelectorAll('template[data-studenthub-notice]')]
            .map(template => [template.dataset.studenthubNotice, template]));
        const region = document.querySelector('#region-main');
        if (!region || !templates.size) {
            return;
        }

        const cardSelector = '[data-region="course-content"][data-course-id]';
        const attach = card => {
            const template = templates.get(card.dataset.courseId);
            if (template && !card.querySelector('.local-studenthub-notices')) {
                (card.querySelector('.course-info-container') ?? card).append(template.content.cloneNode(true));
            }
        };
        region.querySelectorAll(cardSelector).forEach(attach);
        new MutationObserver(records => {
            for (const record of records) {
                for (const element of record.addedNodes) {
                    if (!(element instanceof Element)) {
                        continue;
                    }
                    if (element.matches(cardSelector)) {
                        attach(element);
                    }
                    element.querySelectorAll(cardSelector).forEach(attach);
                }
            }
        }).observe(region, {childList: true, subtree: true});
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once: true});
    } else {
        init();
    }
})();
