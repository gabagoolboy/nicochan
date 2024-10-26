  const customTheme = agGrid
    .createTheme()
    .withPart(agGrid.iconSetQuartzLight)
	  .withParams({
        backgroundColor: "#2B2F36",
        borderRadius: "0px",
        browserColorScheme: "dark",
        chromeBackgroundColor: {
            ref: "foregroundColor",
            mix: 0.07,
            onto: "backgroundColor"
        },
        columnBorder: false,
        fontFamily: "inherit",
        foregroundColor: "#8FA5AEF5",
        headerFontSize: 14,
        sidePanelBorder: false,
        spacing: "5px",
        wrapperBorder: true,
        wrapperBorderRadius: "0px"
    });