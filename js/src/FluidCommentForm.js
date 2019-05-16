'use strict';

import React from 'react';
import { getDeepProp, getResponseDocument } from "./functions";

class FluidCommentForm extends React.Component {

  constructor(props) {
    super(props);
    const values = props.values || { subject: '', body: ''};
    this.state = {
      subjectField: values.subject,
      bodyField: values.body
    };
  }

  render() {
    const { isRefreshing, handleCancel = null } = this.props;
    const { subjectField, bodyField } = this.state;

    return (
      <div>
        <form className="comment-comment-form comment-form" onSubmit={this.handleSubmit}>
          <div className="form-item">
            <label for="subjectField">Subject</label>
            <input
              type="text"
              name="subjectField"
              className="form-text"
              value={subjectField}
              disabled={isRefreshing}
              onChange={this.handleChange}
            />
          </div>
          <div className="text-format-wrapper form-item">
            <div className="form-type-textarea form-item">
              <label for="bodyField" className="form-required">Body</label>
              <div className="form-textarea-wrapper">
                <textarea
                  type="text"
                  name="bodyField"
                  className="form-textarea required resize-vertical"
                  data-editor-active-text-format="basic_html"
                  value={bodyField}
                  disabled={isRefreshing}
                  onChange={this.handleChange}
                />
            </div>
          </div>
          </div>
          <div className="form-actions">
            <button
              className={`button ${isRefreshing && 'is-disabled'}`}
              type="submit"
              disabled={isRefreshing}
            >
              Post Comment
            </button>
            {handleCancel &&
              <button className="button" onClick={handleCancel}>Cancel</button>
            }
          </div>
        </form>
      </div>
    );
  }

  handleChange = (event) => {
    this.setState({[event.target.name]: event.target.value});
  };

  handleSubmit = (event) => {
    event.preventDefault();

    const { subjectField, bodyField } = this.state;

    if (bodyField === '') {
      console.log('Body field is required');
      return;
    }

    this.props.handleSubmit({ subjectField, bodyField });
  }

}

export default FluidCommentForm;
