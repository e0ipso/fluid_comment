'use strict';

import React from 'react';

class InlineLoginForm extends React.Component {

  constructor(props) {
    super(props);
    this.state = {nameField: '', passField: '', failed: false};
    this.handleChange = this.handleChange.bind(this);
    this.handleSubmit = this.handleSubmit.bind(this);
  }

  render() {
    return (
        <form onSubmit={this.handleSubmit}>
          <div className="form-item">
            <label for="nameField">Username</label>
            <input type="text" name="nameField" value={this.state.nameField} onChange={this.handleChange} />
          </div>
          <div className="form-item">
            <label for="passField">Password</label>
            <input type="password" name="passField" value={this.state.passField} onChange={this.handleChange} />
          </div>
          <div className="form-actions">
          <input
            type="submit"
            value="Log In"
            className={`button`}
          />
          </div>
        </form>
    );
  }

  handleChange(event) {
    this.setState({[event.target.name]: event.target.value});
  }

  handleSubmit(event) {
    event.preventDefault();
    const options = {
      method: 'POST',
      body: JSON.stringify({
        name: this.state.nameField,
        pass: this.state.passField
      })
    };
    fetch(this.props.loginUrl, options).then(response => {
      this.props.onLogin(response.ok);
    });
  }

}

export default InlineLoginForm;
