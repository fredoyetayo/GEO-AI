import { useBlockProps, RichText } from '@wordpress/block-editor';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Edit({ attributes, setAttributes }) {
    const { tldr, keyFacts } = attributes;

    const addKeyFact = () => {
        setAttributes({
            keyFacts: [...keyFacts, ''],
        });
    };

    const updateKeyFact = (index, value) => {
        const newKeyFacts = [...keyFacts];
        newKeyFacts[index] = value;
        setAttributes({ keyFacts: newKeyFacts });
    };

    const removeKeyFact = (index) => {
        const newKeyFacts = keyFacts.filter((_, i) => i !== index);
        setAttributes({ keyFacts: newKeyFacts });
    };

    return (
        <div {...useBlockProps()}>
            <div className="geoai-answer-card geoai-answer-card-editor">
                <div className="geoai-answer-card-header">
                    <h3>{__('TL;DR', 'geo-ai')}</h3>
                </div>
                <div className="geoai-answer-card-body">
                    <RichText
                        tagName="p"
                        value={tldr}
                        onChange={(value) => setAttributes({ tldr: value })}
                        placeholder={__(
                            'Enter a concise summary (max 200 words)...',
                            'geo-ai'
                        )}
                        className="geoai-answer-card-tldr"
                    />

                    <div className="geoai-answer-card-facts">
                        <h4>{__('Key Facts', 'geo-ai')}</h4>
                        <ul>
                            {keyFacts.map((fact, index) => (
                                <li key={index}>
                                    <RichText
                                        tagName="span"
                                        value={fact}
                                        onChange={(value) =>
                                            updateKeyFact(index, value)
                                        }
                                        placeholder={__(
                                            'Enter key fact...',
                                            'geo-ai'
                                        )}
                                    />
                                    <Button
                                        isSmall
                                        isDestructive
                                        onClick={() => removeKeyFact(index)}
                                    >
                                        {__('Remove', 'geo-ai')}
                                    </Button>
                                </li>
                            ))}
                        </ul>
                        <Button isPrimary isSmall onClick={addKeyFact}>
                            {__('Add Key Fact', 'geo-ai')}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}
