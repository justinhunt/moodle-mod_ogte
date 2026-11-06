define(['jquery', 'core/log', 'core/ajax'], function ($, log, ajax) {
    "use strict"; // jshint ;_;
    /*
    Helpers shared by the OGTE tools: text stats, coverage and AI calls.
     */

    log.debug('OGTE Utils: initialising');

    return {
        countWords: function (sentence) {
            // Remove any leading or trailing spaces
            sentence = sentence.trim();

            // Count the number of spaces in the sentence
            var spaceCount = (sentence.match(/ /g) || []).length;

            // Add 1 to get the number of words (assuming at least one space)
            return spaceCount + 1;
        },

        levelPassage: function (thepassage, ignore, listid, listlevel, ogteid) {
            var that = this;
            return ajax.call([{
                methodname: 'mod_ogte_get_coverage',
                args: {
                    'ogteid': ogteid,
                    'passage': thepassage,
                    'ignore': ignore,
                    'listid': listid,
                    'listlevel': listlevel
                },
                async: false
            }])[0];
        },


        stripTags: function (input) {
            if (typeof input === "undefined" || input == null) return "";
            return input.replace(/<[^>]*>/g, '');
        },

        getSelectedWord: function () {

            var selectedText = "";
            if (window.getSelection) {
                selectedText = window.getSelection().toString();
            } else if (document.selection && document.selection.type !== "Control") {
                selectedText = document.selection.createRange().text;
            }
            if (selectedText.length > 0) {
                var words = selectedText.split(/\s+/);
                // Get the first word
                if (words.length > 0) {
                    return words[0];
                }
            }
            return selectedText;
        },

        analyzeText: function (text) {

            //tidy it up
            text = this.stripTags(text);

            // Count the number of words
            const words = text.split(/\s+/);
            const wordCount = words.length;
            const totalChars = text.length;

            // Calculate the average word length
            const totalWordLength = words.reduce((acc, word) => acc + word.length, 0);
            const averageWordLength = (totalWordLength / wordCount).toFixed(1);

            // Count the number of sentences
            const sentences = text.split(/[.!?]+/);
            var sentenceCount = sentences.length - 1; // Ignore the last element
            if (sentenceCount < 1 && wordCount > 0) { sentenceCount = 1; }//if no punctuation, then we have one sentence

            // Calculate the average sentence length
            var totalSentenceLength = 0;
            for (var i = 0; i < sentences.length; i++) {
                totalSentenceLength += this.countWords(sentences[i]);
            }
            // const totalSentenceLength = sentences.reduce((acc, sentence) => acc + sentence.length, 0);
            var averageSentenceLength = 0;
            if (sentenceCount > 0) {
                averageSentenceLength = (totalSentenceLength / sentenceCount).toFixed(1);
            }

            var stats = {
                charCount: totalChars,
                wordCount: wordCount,
                avWordLength: averageWordLength,
                sentenceCount: sentenceCount,
                avSentenceLength: averageSentenceLength,
            };
            return stats;
        },

        analyzeOutListLevelsIgnored: function (jsonrating) {
            // We now receive pre-calculated arrays from PHP instead of parsing passage HTML
            var results = {};
            // The JSON from PHP includes outoflist_freq, outoflevel_freq, ignored_freq, propernoun_freq
            results['outoflist'] = jsonrating.outoflist_freq || [];
            results['outoflevel'] = jsonrating.outoflevel_freq || [];
            results['ignored'] = jsonrating.ignored_freq || [];
            results['propernoun'] = jsonrating.propernoun_freq || [];

            return results;
        },

        calc_outoflevel_frequencies: function (jsonrating) {
            // Retrieve pre-calculated level summaries from the JSON rating
            return jsonrating.outoflevel_summary || [];
        },

        cleanWord: function (inputWord) {
            // Convert to lowercase
            var lowercaseWord = inputWord.toLowerCase();
            // Remove leading and trailing whitespace and punctuation
            var cleanedWord = lowercaseWord.replace(/^[^\w]+|[^\w]+$/g, '');
            return cleanedWord;
        },

        downloadTextContent: function (textcontent, fileName) {

            var blob = new Blob([textcontent], { type: 'text/plain' });
            var link = document.createElement('a');

            link.href = window.URL.createObjectURL(blob);
            link.download = fileName;

            // Append the link to the document and trigger a click event to start the download
            document.body.appendChild(link);
            link.click();

            // Remove the link from the document
            document.body.removeChild(link);
        },

        /**
         * Run one of the AI tools on the server, using the site's AI provider.
         *
         * @param {Number} ogteid The activity instance id.
         * @param {String} action generate_article or grammar_correction.
         * @param {String} text The topic or passage.
         * @param {String} language The language code, e.g. en-US.
         * @return {Promise} Resolves to the passage the AI wrote.
         */
        call_ai: function (ogteid, action, text, language) {
            return ajax.call([{
                methodname: 'mod_ogte_call_ai',
                args: {
                    ogteid: ogteid,
                    action: action,
                    text: text,
                    language: language || 'en-US'
                }
            }])[0].then(function (result) {
                return result.text;
            });
        }

    };//end of return value
});