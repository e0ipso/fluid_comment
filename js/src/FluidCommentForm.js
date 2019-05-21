import React, { useState, useContext } from 'react';

import FluidCommentContext from './FluidCommentContext';
import FluidCommentButton from './FluidCommentButton';

const FluidCommentForm = ({ handleSubmit, handleCancel = null, defaultValues = {} }) => {
  const { isRefreshing, filterDefaultFormat } = useContext(FluidCommentContext);
  const { subject = '', body = ''} = defaultValues;

  const [values, setValues] = useState({ subject, body });

  const handleChange = (event) => {
    event.persist();

    const { name, value } = event.target;
    setValues(values => ({ ...values, [name]: value}));
  };

  const formSubmit = (event) => {
    event.preventDefault();

    const { subject, body } = values;

    if (body === '') {
      console.log('Body field is required');
      return;
    }

    handleSubmit({ subject, body, bodyFormat: filterDefaultFormat });
  };

  return (
    <form
      className="comment-comment-form comment-form"
      onSubmit={formSubmit}
    >
      <div className="form-item">
        <label htmlFor="subject">Subject</label>
        <input
          type="text"
          name="subject"
          className="form-text"
          value={values.subject}
          disabled={isRefreshing}
          onChange={handleChange}
        />
      </div>
      <div className="text-format-wrapper form-item">
        <div className="form-type-textarea form-item">
          <label htmlFor="body"
                 className="form-required">Body</label>
          <div className="form-textarea-wrapper">
          <textarea
            type="text"
            name="body"
            className="form-textarea required resize-vertical"
            data-editor-active-text-format="basic_html"
            value={values.body}
            disabled={isRefreshing}
            onChange={handleChange}
          />
          </div>
        </div>
      </div>
      <div className="form-actions">
        <FluidCommentButton text={'Post Comment'} type="submit" />
        {handleCancel &&
          <FluidCommentButton text={'Cancel'} handler={handleCancel} />
        }
      </div>
    </form>
  );
};

export default FluidCommentForm;
